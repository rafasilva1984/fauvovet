<?php

namespace App\Services;

use App\Clinical\DoseCalculator;
use App\Models\Encounter;
use App\Models\IssuedDocument;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientWeight;
use App\Models\PrescriptionItem;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit;
use App\Support\SearchText;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Prescrição estruturada (ADR-022): linhas do formulário → itens com dose calculada pelo peso (catálogo da clínica),
 * avisos para o veterinário conferir (alergia registrada, dose fora da faixa da clínica, espécie, controle especial)
 * e emissão como documento "Receita" (PDF sobre o receituário) com os itens gravados.
 * O sistema faz a conta e mostra os avisos; quem decide e assina é o veterinário.
 */
final class PrescriptionService
{
    public const MAX_ROWS = 12;

    /** Palavras de alertas/medicamentos que não identificam substância (evita falso "alergia" por "uso", "grave"...). */
    private const NOT_SUBSTANCE = ['alergia', 'alergico', 'alergica', 'reacao', 'medicamento', 'demonstracao', 'grave', 'leve', 'uso', 'oral',
        'topico', 'injetavel', 'comprimido', 'suspensao', 'solucao', 'gota', 'veterinario', 'veterinaria', 'cao', 'gato', 'suspeita',
        'ficticia', 'ficticio', 'exemplo', 'teste', 'paciente', 'tutor', 'registrada', 'registrado', 'conhecida', 'possivel'];

    public function __construct(private readonly TenantContext $context, private readonly Audit $audit, private readonly DocumentService $documents) {}

    /** Peso mais recente do paciente (kg) ou null. */
    public function latestWeight(Patient $patient): ?float
    {
        $w = PatientWeight::query()->where('patient_id', $patient->id)->orderByDesc('measured_at')->value('weight_kg');

        return $w === null ? null : (float) $w;
    }

    /** @return Collection<int, Medication> */
    public function catalog(Patient $patient): Collection
    {
        return Medication::query()->where('active', true)->orderBy('name')->get()->filter(fn (Medication $m) => $m->appliesTo($patient->species))->values();
    }

    /**
     * Linhas do formulário → itens prontos (catálogo aplicado, dose calculada) + avisos. Não grava nada.
     * Linha sem medicamento é ignorada. Campos vazios herdam o padrão do catálogo (via, frequência, duração, dose/kg).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{items: list<array<string, mixed>>, warnings: list<string>, errors: array<string, string>}
     */
    public function plan(Patient $patient, array $rows, ?float $weightKg): array
    {
        $ids = array_values(array_filter(array_map(fn ($r) => (string) ($r['medication_id'] ?? ''), $rows)));
        $catalog = $ids === [] ? collect() : Medication::query()->whereIn('id', $ids)->get()->keyBy('id');
        $items = [];
        $errors = [];
        $warnings = [];
        foreach ($rows as $i => $row) {
            $med = $catalog->get((string) ($row['medication_id'] ?? ''));
            $name = $med->name ?? trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $doseKg = self::decimal($row['dose_mg_kg'] ?? null) ?? ($med?->dose_mg_kg !== null ? (float) $med->dose_mg_kg : null);
            $calc = $med ? DoseCalculator::calculate($med->form, $med->concentration_mg !== null ? (float) $med->concentration_mg : null, $doseKg, $weightKg) : null;
            // Dose escrita pelo veterinário prevalece; a que o sistema preencheu antes (dose_auto) é recalculada.
            $typed = trim((string) ($row['dose_text'] ?? ''));
            if ($typed !== '' && $typed === trim((string) ($row['dose_auto'] ?? ''))) {
                $typed = '';
            }
            $doseText = $typed ?: (string) ($calc['text'] ?? '');
            $fromCalc = $calc !== null && $doseText !== '' && $doseText === $calc['text'];
            $continuous = filter_var($row['continuous'] ?? false, FILTER_VALIDATE_BOOL);
            $duration = $continuous ? null : ((int) ($row['duration_days'] ?? 0) ?: ($med?->duration_days ?: null));
            $item = [
                'row' => $i, 'medication' => $med, 'medication_id' => $med?->id, 'name' => $name, 'form' => $med?->form,
                'presentation' => trim((string) ($row['presentation'] ?? '')) ?: ($med ? ($med->presentation ?: $med->defaultPresentation()) : null),
                'concentration_mg' => $med?->concentration_mg, 'weight_kg' => $fromCalc ? $weightKg : null, 'dose_mg_kg' => $fromCalc ? $doseKg : null,
                'calc' => $calc, 'calc_dose_mg_kg' => $doseKg, 'from_calc' => $fromCalc, 'dose_text' => mb_substr($doseText, 0, 160),
                'route' => trim((string) ($row['route'] ?? '')) ?: (string) $med?->route,
                'frequency' => trim((string) ($row['frequency'] ?? '')) ?: (string) $med?->frequency,
                'duration_days' => $duration, 'continuous' => $continuous,
                'instructions' => trim((string) ($row['instructions'] ?? '')) ?: null,
                'controlled' => (bool) $med?->controlled,
            ];
            $label = ($i + 1).'. '.$name;
            if ($item['dose_text'] === '') {
                $errors["rows.{$i}.dose_text"] = $med && $doseKg !== null && $weightKg === null
                    ? "{$label}: informe o peso do paciente para calcular a dose, ou escreva a dose."
                    : "{$label}: informe a dose.";
            }
            if ($item['route'] === '') {
                $errors["rows.{$i}.route"] = "{$label}: escolha a via.";
            }
            if ($item['frequency'] === '') {
                $errors["rows.{$i}.frequency"] = "{$label}: escolha a frequência.";
            }
            if ($calc !== null && $calc['too_small']) {
                $warnings[] = "{$label}: a dose calculada é menor que a menor fração possível desta apresentação. Considere outra concentração.";
            }
            if ($med && $calc !== null && $fromCalc) {
                $min = $med->dose_min_mg_kg !== null ? (float) $med->dose_min_mg_kg : null;
                $max = $med->dose_max_mg_kg !== null ? (float) $med->dose_max_mg_kg : null;
                $eff = $calc['effective_mg_kg'];
                if (($max !== null && $eff > $max + 1e-9) || ($min !== null && $eff < $min - 1e-9 && ! $calc['too_small'])) {
                    $range = collect([$min !== null ? Medication::num($min) : null, $max !== null ? Medication::num($max) : null])->filter()->join(' a ');
                    $warnings[] = "{$label}: dose efetiva de ".Medication::num($eff, 2).' mg/kg fora da faixa cadastrada pela clínica ('.$range.' mg/kg).';
                }
            }
            if ($med && ! $med->appliesTo($patient->species)) {
                $warnings[] = "{$label}: no catálogo da clínica este medicamento não está indicado para ".mb_strtolower(Species::label($patient->species)).'.';
            }
            $items[] = $item;
        }
        if ($items === []) {
            $errors['rows'] = 'Inclua ao menos um medicamento.';
        }
        foreach ($this->allergyConflicts($patient, $items) as $msg) {
            $warnings[] = $msg;
        }
        if (collect($items)->contains('controlled', true)) {
            $warnings[] = 'Há medicamento sujeito a controle especial. A receita de controle especial (duas vias, modelo oficial) ainda não é emitida pelo FauvoVet: confira a exigência antes de entregar esta receita.';
        }

        return ['items' => $items, 'warnings' => $warnings, 'errors' => $errors];
    }

    /**
     * Alergias registradas que citam a substância, o princípio ativo ou a classe do medicamento.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public function allergyConflicts(Patient $patient, array $items): array
    {
        $alerts = PatientAlert::query()->where('patient_id', $patient->id)->where('kind', 'allergy')->whereNull('resolved_at')->get(['description']);
        if ($alerts->isEmpty()) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            /** @var Medication|null $med */
            $med = $item['medication'];
            $mine = self::substanceTerms(implode(' ', array_filter([$item['name'], $med?->active_ingredient, $med?->drug_class])));
            foreach ($alerts as $alert) {
                if (array_intersect($mine, self::substanceTerms($alert->description)) !== []) {
                    $out[] = ($item['row'] + 1).'. '.$item['name'].': o paciente tem alergia registrada — “'.$alert->description.'”.';
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Texto da receita (vai ao PDF e à busca clínica). Agrupado por via, como é costume nas receitas.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function body(Patient $patient, array $items, ?float $weightKg, ?string $guidance): string
    {
        $species = Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : '');
        $head = 'Uso veterinário — '.$patient->name.' ('.$species.($weightKg !== null ? ', '.Medication::num($weightKg, 3).' kg' : '').')';
        $lines = [$head];
        $n = 0;
        foreach (collect($items)->groupBy('route') as $route => $group) {
            $lines[] = '';
            $lines[] = 'VIA '.mb_strtoupper((string) $route);
            foreach ($group as $item) {
                $n++;
                $title = $n.'. '.$item['name'].($item['presentation'] ? ' — '.$item['presentation'] : '').($item['controlled'] ? ' (controle especial)' : '');
                $duration = match (true) {
                    $item['continuous'] => 'uso contínuo',
                    $item['duration_days'] !== null => 'por '.$item['duration_days'].' '.($item['duration_days'] === 1 ? 'dia' : 'dias'),
                    default => null,
                };
                $lines[] = $title;
                $lines[] = '   '.collect([$item['dose_text'], mb_strtolower($item['frequency']), $duration])->filter()->join(', ').'.';
                if ($item['instructions']) {
                    $lines[] = '   '.$item['instructions'];
                }
            }
        }
        if (trim((string) $guidance) !== '') {
            $lines[] = '';
            $lines[] = 'Orientações: '.trim((string) $guidance);
        }

        return implode("\n", $lines);
    }

    /**
     * Emite a receita: documento (PDF sobre o receituário, auditado) + itens, na mesma transação.
     *
     * @param  list<array<string, mixed>>  $items  saída de plan() sem erros
     */
    public function issue(Patient $patient, ?Encounter $encounter, array $items, ?float $weightKg, ?string $guidance, User $issuer, string $background = 'auto'): IssuedDocument
    {
        if ($items === []) {
            throw ValidationException::withMessages(['rows' => 'Inclua ao menos um medicamento.']);
        }
        $unit = ($encounter ? Unit::query()->find($encounter->unit_id) : null) ?? Unit::query()->find((string) $this->context->unitId());
        $today = Carbon::now($unit->timezone ?? 'America/Sao_Paulo')->startOfDay();
        $document = null;
        try {
            return DB::transaction(function () use ($patient, $encounter, $items, $weightKg, $guidance, $issuer, $background, $today, &$document) {
                $document = $this->documents->issue($patient, $encounter, null, 'prescription', 'Receituário', $this->body($patient, $items, $weightKg, $guidance), $issuer, $background);
                foreach ($items as $pos => $item) {
                    PrescriptionItem::create([
                        'document_id' => $document->id, 'patient_id' => $patient->id, 'encounter_id' => $encounter?->id, 'medication_id' => $item['medication_id'],
                        'position' => $pos + 1, 'name' => mb_substr($item['name'], 0, 160), 'form' => $item['form'],
                        'presentation' => $item['presentation'] ? mb_substr($item['presentation'], 0, 160) : null, 'concentration_mg' => $item['concentration_mg'],
                        'weight_kg' => $item['weight_kg'], 'dose_mg_kg' => $item['dose_mg_kg'], 'dose_mg' => $item['from_calc'] ? $item['calc']['dose_mg'] : null,
                        'quantity' => $item['from_calc'] ? $item['calc']['quantity'] : null, 'dose_text' => $item['dose_text'], 'route' => $item['route'],
                        'frequency' => $item['frequency'], 'duration_days' => $item['duration_days'], 'continuous' => $item['continuous'],
                        'instructions' => $item['instructions'] ? mb_substr($item['instructions'], 0, 500) : null, 'controlled' => $item['controlled'],
                        'starts_on' => $today, 'ends_on' => $item['duration_days'] ? $today->copy()->addDays($item['duration_days'] - 1) : null,
                        'created_by' => $issuer->id,
                    ]);
                }
                $this->audit->record('prescription.issued', $issuer->id, 'document', $document->id, ['patient_id' => $patient->id, 'items' => count($items)]);

                return $document;
            });
        } catch (\Throwable $e) {
            if ($document instanceof IssuedDocument) {
                Storage::disk('local')->delete($document->pdf_path); // a transação desfez o registro: não deixa PDF órfão
            }

            throw $e;
        }
    }

    /**
     * Medicamentos em uso: itens de receitas válidas com duração em curso ou uso contínuo.
     *
     * @return Collection<int, PrescriptionItem>
     */
    public function current(Patient $patient): Collection
    {
        $today = Carbon::today()->toDateString();

        return PrescriptionItem::query()->where('patient_id', $patient->id)
            ->where(fn ($q) => $q->where('continuous', true)->orWhere('ends_on', '>=', $today))
            ->whereIn('document_id', fn ($q) => $q->select('id')->from('issued_documents')->where('tenant_id', $this->context->requireId())
                ->where('patient_id', $patient->id)->whereNull('voided_at'))
            ->orderByDesc('starts_on')->orderBy('position')->limit(30)->get();
    }

    /** "12,5" / "12.5" → 12.5; vazio ou inválido → null. */
    public static function decimal(mixed $v): ?float
    {
        $s = str_replace(',', '.', trim((string) $v));

        return is_numeric($s) && (float) $s > 0 ? (float) $s : null;
    }

    /** @return list<string> */
    private static function substanceTerms(string $text): array
    {
        return array_values(array_filter(SearchText::terms($text), fn (string $t) => strlen($t) >= 4 && ! in_array($t, self::NOT_SUBSTANCE, true)));
    }
}
