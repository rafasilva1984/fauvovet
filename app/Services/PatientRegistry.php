<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Patient;
use App\Support\Audit;
use App\Support\BrazilianDocument;
use App\Support\BrazilianStates;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de responsáveis e pacientes da clínica ativa: regras de validação, normalização (só dígitos em
 * documentos/telefones), aviso de possíveis duplicados (somente dentro da clínica) e auditoria sem dados pessoais.
 */
final class PatientRegistry
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    /**
     * Regras do responsável. $prefix permite o cadastro rápido dentro do formulário de paciente (ex.: "g_").
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function guardianRules(array $input, string $prefix = ''): array
    {
        $pf = ($input[$prefix.'person_type'] ?? 'PF') !== 'PJ';

        return [
            $prefix.'name' => ['required', 'string', 'min:2', 'max:160'],
            $prefix.'person_type' => ['nullable', Rule::in(['PF', 'PJ'])],
            $prefix.'document' => ['nullable', 'string', 'max:20', function (string $attr, mixed $value, Closure $fail) use ($pf) {
                $ok = $pf ? BrazilianDocument::isValidCpf((string) $value) : BrazilianDocument::isValidCnpj((string) $value);
                if (! $ok) {
                    $fail($pf ? 'CPF inválido.' : 'CNPJ inválido.');
                }
            }],
            $prefix.'email' => ['nullable', 'string', 'email:rfc', 'max:191', 'required_without:'.$prefix.'phone'],
            $prefix.'phone' => ['nullable', 'string', 'max:25', 'required_without:'.$prefix.'email', $this->digitsRule(10, 13, 'Telefone com DDD: 10 ou 11 dígitos.')],
            $prefix.'phone_alt' => ['nullable', 'string', 'max:25', $this->digitsRule(8, 13, 'Telefone inválido.')],
            $prefix.'postal_code' => ['nullable', 'string', 'max:10', $this->digitsRule(8, 8, 'CEP com 8 dígitos.')],
            $prefix.'address_line' => ['nullable', 'string', 'max:200'],
            $prefix.'address_number' => ['nullable', 'string', 'max:20'],
            $prefix.'address_complement' => ['nullable', 'string', 'max:80'],
            $prefix.'district' => ['nullable', 'string', 'max:80'],
            $prefix.'city' => ['nullable', 'string', 'max:80'],
            $prefix.'state' => ['nullable', 'string', Rule::in(BrazilianStates::codes())],
            $prefix.'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function guardianMessages(string $prefix = ''): array
    {
        return [
            $prefix.'email.required_without' => 'Informe telefone ou e-mail para contato.',
            $prefix.'phone.required_without' => 'Informe telefone ou e-mail para contato.',
            $prefix.'state.in' => 'Escolha a UF na lista.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  dados validados
     * @return array<string, mixed> atributos do model
     */
    public function guardianAttributes(array $data, string $prefix = ''): array
    {
        $digits = fn (string $key) => isset($data[$prefix.$key]) && $data[$prefix.$key] !== '' ? BrazilianDocument::digits((string) $data[$prefix.$key]) : null;

        return [
            'name' => trim((string) $data[$prefix.'name']),
            'person_type' => ($data[$prefix.'person_type'] ?? 'PF') === 'PJ' ? 'PJ' : 'PF',
            'document_number' => $digits('document'),
            'email' => isset($data[$prefix.'email']) ? mb_strtolower(trim((string) $data[$prefix.'email'])) : null,
            'phone' => $digits('phone'),
            'phone_alt' => $digits('phone_alt'),
            'postal_code' => $digits('postal_code'),
            'address_line' => $data[$prefix.'address_line'] ?? null,
            'address_number' => $data[$prefix.'address_number'] ?? null,
            'address_complement' => $data[$prefix.'address_complement'] ?? null,
            'district' => $data[$prefix.'district'] ?? null,
            'city' => $data[$prefix.'city'] ?? null,
            'state' => isset($data[$prefix.'state']) ? strtoupper((string) $data[$prefix.'state']) : null,
            'notes' => $data[$prefix.'notes'] ?? null,
        ];
    }

    /**
     * Responsáveis da clínica ativa com o mesmo CPF/CNPJ ou telefone (aviso de duplicidade; nunca de outra clínica).
     *
     * @param  array<string, mixed>  $attrs
     * @return Collection<int, Guardian>
     */
    public function guardianDuplicates(array $attrs, ?string $exceptId = null): Collection
    {
        if ($attrs['document_number'] === null && $attrs['phone'] === null) {
            return new Collection;
        }

        return Guardian::query()
            ->where(function ($q) use ($attrs) {
                if ($attrs['document_number'] !== null) {
                    $q->orWhere('document_number', $attrs['document_number']);
                }
                if ($attrs['phone'] !== null) {
                    $q->orWhere('phone', $attrs['phone'])->orWhere('phone_alt', $attrs['phone']);
                }
            })
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('name')->limit(5)->get(['id', 'name', 'phone']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function createGuardian(array $attrs, string $actorId): Guardian
    {
        $guardian = Guardian::create([...$attrs, 'created_by' => $actorId]);
        $this->audit->record('guardian.created', $actorId, 'guardian', $guardian->id);

        return $guardian;
    }

    /** @param  array<string, mixed>  $attrs */
    public function updateGuardian(Guardian $guardian, array $attrs, string $status, string $actorId): void
    {
        $guardian->fill([...$attrs, 'status' => $status]);
        $changed = array_keys($guardian->getDirty());
        $guardian->save();
        $this->audit->record('guardian.updated', $actorId, 'guardian', $guardian->id, ['fields' => $changed]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function patientRules(array $input): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'species' => ['required', Rule::in(Species::codes())],
            'species_detail' => ['nullable', 'string', 'max:120'],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['required', Rule::in(array_keys(Patient::SEX))],
            'neutered' => ['required', Rule::in(array_keys(Patient::NEUTERED))],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before_or_equal:today'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_months' => ['nullable', 'integer', 'min:0', 'max:11'],
            'birth_date_estimated' => ['nullable', 'boolean'],
            'coat' => ['nullable', 'string', 'max:80'],
            'microchip' => ['nullable', 'string', 'regex:/^[A-Za-z0-9 .-]{3,30}$/'],
            'marks' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(array_keys(Patient::STATUS))],
            'deceased_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'required_if:status,deceased'],
        ];
    }

    /** @return array<string, string> */
    public function patientMessages(): array
    {
        return [
            'microchip.regex' => 'Microchip: de 3 a 30 letras ou números.',
            'deceased_on.required_if' => 'Informe a data do óbito.',
            'birth_date.before_or_equal' => 'A data de nascimento não pode ser no futuro.',
        ];
    }

    /**
     * Converte os dados validados nos atributos do paciente. Sem data de nascimento, mas com idade aproximada,
     * calcula uma data ESTIMADA (sinalizada na interface com "~").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patientAttributes(array $data): array
    {
        $birth = $data['birth_date'] ?? null;
        $estimated = (bool) ($data['birth_date_estimated'] ?? false);
        if (! $birth && (($data['age_years'] ?? null) !== null || ($data['age_months'] ?? null) !== null)) {
            $birth = Carbon::today()->subYears((int) ($data['age_years'] ?? 0))->subMonths((int) ($data['age_months'] ?? 0))->format('Y-m-d');
            $estimated = true;
        }
        $status = $data['status'] ?? 'active';
        $deceasedOn = $status === 'deceased' ? ($data['deceased_on'] ?? null) : null;
        if ($deceasedOn !== null && $birth !== null && $deceasedOn < $birth) {
            throw ValidationException::withMessages(['deceased_on' => 'A data do óbito não pode ser anterior ao nascimento.']);
        }

        return [
            'name' => trim((string) $data['name']),
            'species' => $data['species'],
            'species_detail' => $data['species_detail'] ?? null,
            'breed' => $data['breed'] ?? null,
            'sex' => $data['sex'],
            'neutered' => $data['neutered'],
            'birth_date' => $birth,
            'birth_date_estimated' => $birth !== null && $estimated,
            'coat' => $data['coat'] ?? null,
            'microchip' => isset($data['microchip']) ? strtoupper(str_replace([' ', '.', '-'], '', (string) $data['microchip'])) : null,
            'marks' => $data['marks'] ?? null,
            'status' => $status,
            'deceased_on' => $deceasedOn,
        ];
    }

    /**
     * Cria o paciente já vinculado ao responsável (mesma clínica, garantido também pela FK composta).
     *
     * @param  array<string, mixed>  $attrs
     */
    public function createPatient(array $attrs, Guardian $guardian, string $relationship, string $actorId): Patient
    {
        return DB::transaction(function () use ($attrs, $guardian, $relationship, $actorId) {
            $patient = Patient::create([...$attrs, 'created_by' => $actorId]);
            $this->attach($patient, $guardian, $relationship);
            $this->audit->record('patient.created', $actorId, 'patient', $patient->id, ['species' => $patient->species, 'guardian_id' => $guardian->id]);

            return $patient;
        });
    }

    /** @param  array<string, mixed>  $attrs */
    public function updatePatient(Patient $patient, array $attrs, string $actorId): void
    {
        $patient->fill($attrs);
        $changed = array_keys($patient->getDirty());
        $patient->save();
        $this->audit->record($patient->wasChanged('status') && $patient->status === 'deceased' ? 'patient.deceased' : 'patient.updated', $actorId, 'patient', $patient->id, ['fields' => $changed]);
    }

    public function link(Patient $patient, Guardian $guardian, string $relationship, string $actorId): void
    {
        if ($patient->guardians()->whereKey($guardian->id)->exists()) {
            throw ValidationException::withMessages(['guardian_id' => 'Este responsável já está vinculado ao paciente.']);
        }
        $this->attach($patient, $guardian, $relationship);
        $this->audit->record('patient.guardian_linked', $actorId, 'patient', $patient->id, ['guardian_id' => $guardian->id, 'relationship' => $relationship]);
    }

    public function unlink(Patient $patient, Guardian $guardian, string $actorId): void
    {
        if ($patient->guardians()->count() <= 1) {
            throw ValidationException::withMessages(['guardian_id' => 'O paciente precisa de ao menos um responsável. Vincule outro antes de remover este.']);
        }
        // DELETE explícito com tenant_id (o firewall de SQL exige o filtro da clínica também no vínculo).
        DB::table('guardian_patient')->where('tenant_id', $this->context->requireId())
            ->where('patient_id', $patient->id)->where('guardian_id', $guardian->id)->delete();
        $this->audit->record('patient.guardian_unlinked', $actorId, 'patient', $patient->id, ['guardian_id' => $guardian->id]);
    }

    private function attach(Patient $patient, Guardian $guardian, string $relationship): void
    {
        $patient->guardians()->attach($guardian->id, ['tenant_id' => $this->context->requireId(), 'relationship' => $relationship]);
    }

    private function digitsRule(int $min, int $max, string $message): Closure
    {
        return function (string $attr, mixed $value, Closure $fail) use ($min, $max, $message) {
            $len = strlen(BrazilianDocument::digits((string) $value));
            if ($len < $min || $len > $max) {
                $fail($message);
            }
        };
    }
}
