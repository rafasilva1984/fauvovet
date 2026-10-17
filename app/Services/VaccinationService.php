<?php

namespace App\Services;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vacinação da clínica ativa: catálogo (modelos iniciais editáveis), registro com cálculo da próxima dose,
 * anulação auditada, situação da carteira e lista de vencimentos (atrasadas/próximas).
 */
final class VaccinationService
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function ensureDefaults(): void
    {
        if (Vaccine::query()->exists()) {
            return;
        }
        DB::transaction(function () {
            foreach (Vaccine::DEFAULTS as [$name, $species, $doses, $interval, $booster]) {
                Vaccine::create(['name' => $name, 'species' => $species, 'series_doses' => $doses, 'series_interval_days' => $interval,
                    'booster_interval_days' => $booster, 'active' => true,
                    'notes' => 'Modelo genérico: revise doses e intervalos com o protocolo da clínica e a bula do fabricante.']);
            }
        });
    }

    /** Próxima dose: dentro da série → intervalo da série; série completa → reforço (ou nenhuma). */
    public static function nextDue(Vaccine $vaccine, int $dose, Carbon $appliedOn): ?Carbon
    {
        if ($dose < $vaccine->series_doses) {
            return $appliedOn->copy()->addDays(max(1, $vaccine->series_interval_days));
        }

        return $vaccine->booster_interval_days ? $appliedOn->copy()->addDays($vaccine->booster_interval_days) : null;
    }

    /** Número da próxima dose para o paciente (aplicações válidas desta vacina + 1; reinicia após reforço). */
    public function suggestedDose(Patient $patient, Vaccine $vaccine): int
    {
        $count = Vaccination::query()->where('patient_id', $patient->id)->where('vaccine_id', $vaccine->id)->whereNull('voided_at')->count();

        return min(99, $count + 1);
    }

    /**
     * @param  array{applied_on: string, lot: string, manufacturer: ?string, lot_expires_on: ?string, site: ?string, notes: ?string, dose_number: ?int, next_due_on: ?string}  $data
     */
    public function register(Patient $patient, Vaccine $vaccine, ?Encounter $encounter, array $data, User $actor): Vaccination
    {
        if (! $vaccine->appliesTo($patient->species)) {
            throw ValidationException::withMessages(['vaccine_id' => 'Esta vacina não está indicada para a espécie do paciente no catálogo da clínica.']);
        }
        $applied = Carbon::parse($data['applied_on'])->startOfDay();
        if (! empty($data['lot_expires_on']) && Carbon::parse($data['lot_expires_on'])->lt($applied)) {
            throw ValidationException::withMessages(['lot_expires_on' => 'O lote já estava vencido na data da aplicação.']);
        }
        if ($encounter && $encounter->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['vaccine_id' => 'O atendimento é de outro paciente.']);
        }
        $dose = (int) ($data['dose_number'] ?? 0) ?: $this->suggestedDose($patient, $vaccine);
        $next = ! empty($data['next_due_on']) ? Carbon::parse($data['next_due_on']) : self::nextDue($vaccine, $dose > $vaccine->series_doses ? $vaccine->series_doses : $dose, $applied);

        $v = Vaccination::create([
            'patient_id' => $patient->id, 'vaccine_id' => $vaccine->id, 'encounter_id' => $encounter?->id, 'dose_number' => $dose,
            'applied_on' => $applied, 'lot' => trim($data['lot']), 'manufacturer' => $data['manufacturer'] ?? $vaccine->manufacturer,
            'lot_expires_on' => $data['lot_expires_on'] ?? null, 'site' => $data['site'] ?? null, 'next_due_on' => $next,
            'notes' => $data['notes'] ?? null, 'applied_by_membership_id' => (string) $this->context->membership()?->id, 'created_by' => $actor->id,
        ]);
        $this->audit->record('vaccination.registered', $actor->id, 'patient', $patient->id, ['vaccination_id' => $v->id, 'vaccine_id' => $vaccine->id, 'dose' => $dose]);

        return $v;
    }

    public function void(Vaccination $vaccination, string $reason, User $actor): void
    {
        if ($vaccination->voided_at !== null) {
            return;
        }
        $vaccination->voided_at = now();
        $vaccination->setAttribute('voided_by', $actor->id);
        $vaccination->void_reason = mb_substr($reason, 0, 200);
        $vaccination->save();
        $this->audit->record('vaccination.voided', $actor->id, 'patient', $vaccination->patient_id, ['vaccination_id' => $vaccination->id]);
    }

    /**
     * Situação por vacina na carteira: última aplicação válida e status da próxima dose.
     *
     * @return list<array{vaccine: Vaccine, last: Vaccination, status: string, days: ?int}>
     */
    public function card(Patient $patient): array
    {
        $latest = Vaccination::query()->where('patient_id', $patient->id)->whereNull('voided_at')->with('vaccine')
            ->orderByDesc('applied_on')->orderByDesc('created_at')->get()->unique('vaccine_id');
        $today = Carbon::today();

        return $latest->map(function (Vaccination $v) use ($today) {
            $days = $v->next_due_on ? (int) $today->diffInDays($v->next_due_on, false) : null;
            $status = match (true) {
                $days === null => 'complete',
                $days < 0 => 'overdue',
                $days <= 30 => 'soon',
                default => 'ok',
            };

            return ['vaccine' => $v->vaccine, 'last' => $v, 'status' => $status, 'days' => $days];
        })->sortBy(fn ($r) => [['overdue' => 0, 'soon' => 1, 'ok' => 2, 'complete' => 3][$r['status']], $r['vaccine']->name])->values()->all();
    }

    /**
     * Vencimentos da clínica: última aplicação válida de cada paciente+vacina com próxima dose até $days dias
     * (inclui atrasadas). Só pacientes ativos.
     *
     * @return Collection<int, Vaccination>
     */
    public function dueList(int $days = 30, int $limit = 50): Collection
    {
        $tenant = $this->context->requireId();

        // Sem alias na tabela principal: o escopo da clínica qualifica "vaccinations.tenant_id".
        return Vaccination::query()
            ->whereNull('vaccinations.voided_at')->whereNotNull('vaccinations.next_due_on')
            ->where('vaccinations.next_due_on', '<=', Carbon::today()->addDays($days))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('vaccinations as n')
                ->where('n.tenant_id', $tenant)->whereColumn('n.patient_id', 'vaccinations.patient_id')
                ->whereColumn('n.vaccine_id', 'vaccinations.vaccine_id')->whereNull('n.voided_at')
                ->whereColumn('n.applied_on', '>', 'vaccinations.applied_on'))
            ->whereIn('vaccinations.patient_id', fn ($q) => $q->select('id')->from('patients')->where('tenant_id', $tenant)->where('status', 'active'))
            ->with(['vaccine:id,name', 'patient:id,name,species', 'patient.guardians:id,name,phone'])
            ->orderBy('vaccinations.next_due_on')->limit($limit)->get();
    }
}
