<?php

namespace App\Services;

use App\Models\ClinicalCase;
use App\Models\ClinicalCasePatient;
use App\Models\Encounter;
use App\Models\EncounterDiagnosis;
use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use App\Support\Species;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Casos científicos (pedido do proprietário): a clínica transforma casos já tratados em séries e relatos.
 * - Série: pacientes vindos da busca avançada (critérios gravados para reprodutibilidade).
 * - Relato: um paciente a partir de um atendimento finalizado.
 * - Exportação ANONIMIZADA: pseudônimo, mês/ano, idade na referência; nunca nome, responsável, documento, microchip,
 *   datas exatas ou texto livre do prontuário. Observações do veterinário passam por redação de nomes conhecidos.
 * - Só casos com consentimento do tutor ("Autorizado") entram na exportação.
 */
final class ClinicalCaseService
{
    private const TZ = 'America/Sao_Paulo';

    public function __construct(private readonly PatientSearch $search, private readonly Audit $audit) {}

    /**
     * @param  array<string, mixed>  $filters  filtros normalizados de PatientSearch
     * @param  array{title: string, question: string, specialty?: string|null}  $data
     */
    public function createSeries(array $filters, array $data, User $actor): ClinicalCase
    {
        $ids = $this->search->query($filters)->orderBy('id')->limit(ClinicalCase::MAX_PATIENTS)->pluck('id')->all();
        $refs = $this->search->referenceEncounters($filters, $ids);
        $criteria = array_filter($filters, fn ($v) => $v !== '' && $v !== null && $v !== false);

        return $this->create('series', $data, $criteria, array_map(fn ($id) => [$id, $refs[$id] ?? null], $ids), $actor);
    }

    /** @param array{title: string, question: string, specialty?: string|null} $data */
    public function createReport(Encounter $encounter, array $data, User $actor): ClinicalCase
    {
        return $this->create('report', $data, null, [[$encounter->patient_id, $encounter->id]], $actor);
    }

    /**
     * @param  array{title: string, question: string, specialty?: string|null}  $data
     * @param  array<string, mixed>|null  $criteria
     * @param  list<array{0: string, 1: string|null}>  $patients
     */
    private function create(string $kind, array $data, ?array $criteria, array $patients, User $actor): ClinicalCase
    {
        $case = DB::transaction(function () use ($kind, $data, $criteria, $patients, $actor) {
            $case = ClinicalCase::create([
                'kind' => $kind, 'title' => $data['title'], 'question' => $data['question'], 'specialty' => $data['specialty'] ?? null,
                'criteria' => $criteria, 'status' => 'open', 'created_by' => $actor->id,
            ]);
            foreach ($patients as $i => [$patientId, $encounterId]) {
                ClinicalCasePatient::create([
                    'case_id' => $case->id, 'patient_id' => $patientId, 'encounter_id' => $encounterId,
                    'label' => self::label($i + 1), 'added_by' => $actor->id,
                ]);
            }
            $case->next_label = count($patients) + 1;
            $case->save();

            return $case;
        });
        $this->audit->record('clinical_case.created', $actor->id, 'clinical_case', $case->id, ['kind' => $kind, 'patients' => count($patients)]);

        return $case;
    }

    public static function label(int $n): string
    {
        return 'Caso '.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{outcome: string, notes?: string|null, consent: string, consent_on?: string|null}  $data
     */
    public function updateEntry(ClinicalCasePatient $entry, array $data, User $actor): void
    {
        $consentChanged = $entry->consent !== $data['consent'];
        $entry->fill([
            'outcome' => $data['outcome'], 'notes' => $data['notes'] ?? null, 'consent' => $data['consent'],
            'consent_on' => $data['consent'] === 'pending' ? null : ($data['consent_on'] ?? ($consentChanged ? now(self::TZ)->toDateString() : $entry->consent_on)),
        ]);
        if ($consentChanged) {
            $entry->consent_recorded_by = $actor->id;
        }
        $entry->save();
        if ($consentChanged) {
            $this->audit->record('clinical_case.consent_recorded', $actor->id, 'clinical_case', $entry->case_id, ['entry' => $entry->id, 'consent' => $entry->consent]);
        }
    }

    /**
     * Linhas da exportação anonimizada (somente casos com consentimento).
     *
     * @return array{rows: list<array<string, string>>, excluded: int}
     */
    public function anonymizedRows(ClinicalCase $case): array
    {
        $entries = $case->entries()->with(['patient.guardians:id,name', 'encounter:id,started_at,weight_kg'])->get();
        $granted = $entries->where('consent', 'granted');
        $dx = EncounterDiagnosis::query()->whereIn('encounter_id', $granted->pluck('encounter_id')->filter()->all())
            ->orderBy('position')->get(['encounter_id', 'description', 'code', 'status'])->groupBy('encounter_id');
        $names = $this->knownNames($entries);
        $rows = [];
        foreach ($granted as $e) {
            $p = $e->patient;
            $ref = $e->encounter?->started_at;
            $rows[] = [
                'caso' => $e->label,
                'especie' => Species::label($p->species).($p->species_detail ? ' — '.$p->species_detail : ''),
                'raca' => (string) ($p->breed ?? ''),
                'sexo' => Patient::SEX[$p->sex] ?? '',
                'castracao' => Patient::NEUTERED[$p->neutered] ?? '',
                'idade_referencia' => self::ageAt($p, $ref),
                'mes_referencia' => $ref ? Carbon::parse($ref)->timezone(self::TZ)->format('m/Y') : '',
                'peso_kg' => $e->encounter?->weight_kg !== null ? rtrim(rtrim(number_format((float) $e->encounter->weight_kg, 2, ',', ''), '0'), ',') : '',
                'diagnosticos' => collect($dx[$e->encounter_id] ?? [])->map(fn ($d) => $d->description.($d->code ? " [{$d->code}]" : '').' ('.mb_strtolower(EncounterDiagnosis::STATUS[$d->status]).')')->join('; '),
                'desfecho' => ClinicalCasePatient::OUTCOMES[$e->outcome],
                'observacoes' => self::redact((string) $e->notes, $names),
            ];
        }

        return ['rows' => $rows, 'excluded' => $entries->count() - $granted->count()];
    }

    /**
     * Estatísticas agregadas (nada individual): distribuição por espécie, sexo, desfecho e consentimento; idades.
     *
     * @return array<string, mixed>
     */
    public function stats(ClinicalCase $case): array
    {
        $entries = $case->entries()->with(['patient:id,species,sex,birth_date,birth_date_estimated,deceased_on', 'encounter:id,started_at'])->get();
        $count = fn (Collection $c) => $c->countBy()->sortDesc()->all();
        $ages = $entries->map(fn ($e) => $e->patient->birth_date ? $e->patient->birth_date->diffInMonths($e->encounter->started_at ?? now(), true) / 12 : null)->filter(fn ($a) => $a !== null)->sort()->values();

        return [
            'total' => $entries->count(),
            'species' => $count($entries->map(fn ($e) => Species::label($e->patient->species))),
            'sex' => $count($entries->map(fn ($e) => Patient::SEX[$e->patient->sex])),
            'outcome' => $count($entries->map(fn ($e) => ClinicalCasePatient::OUTCOMES[$e->outcome])),
            'consent' => $count($entries->map(fn ($e) => ClinicalCasePatient::CONSENT[$e->consent])),
            'age' => $ages->isEmpty() ? null : [
                'n' => $ages->count(), 'min' => $ages->first(), 'max' => $ages->last(),
                'median' => $ages->count() % 2 ? $ages[intdiv($ages->count(), 2)] : ($ages[$ages->count() / 2 - 1] + $ages[$ages->count() / 2]) / 2,
            ],
        ];
    }

    public static function ageAt(Patient $p, mixed $at): string
    {
        if ($p->birth_date === null) {
            return '';
        }
        $months = (int) $p->birth_date->diffInMonths($at ? Carbon::parse($at) : now(), true);

        return ($p->birth_date_estimated ? '~' : '').($months >= 12 ? intdiv($months, 12).(intdiv($months, 12) === 1 ? ' ano' : ' anos') : $months.($months === 1 ? ' mês' : ' meses'));
    }

    /**
     * Nomes de pacientes e responsáveis do caso (≥ 3 letras), para redigir textos livres escritos pela equipe.
     *
     * @param  Collection<int, ClinicalCasePatient>  $entries
     * @return list<string>
     */
    public function knownNames(Collection $entries): array
    {
        $names = [];
        foreach ($entries as $e) {
            $names[] = $e->patient->name;
            foreach ($e->patient->guardians as $g) {
                array_push($names, ...preg_split('/\s+/u', preg_replace('/\(.*?\)/u', '', $g->name) ?? '') ?: []);
            }
        }

        $particles = ['das', 'dos', 'del', 'van', 'von'];

        return array_values(array_unique(array_filter(array_map('trim', $names), fn ($n) => mb_strlen($n) >= 3 && ! in_array(mb_strtolower($n), $particles, true))));
    }

    /** @param list<string> $names */
    public static function redact(string $text, array $names): string
    {
        // Primeiro e-mails e sequências longas de dígitos (telefone, CPF, microchip); depois nomes conhecidos.
        $text = (string) preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[e-mail omitido]', $text);
        $text = (string) preg_replace('/\d[\d .\/()-]{6,}\d/u', '[número omitido]', $text);
        foreach ($names as $n) {
            $text = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($n, '/').'(?![\p{L}\p{N}])/iu', '[nome omitido]', $text);
        }

        return $text;
    }
}
