<?php

namespace App\Services;

use App\Http\Controllers\GuardianController;
use App\Models\Encounter;
use App\Models\EncounterDiagnosis;
use App\Models\Guardian;
use App\Models\Patient;
use App\Support\BrazilianDocument;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Busca avançada de pacientes da clínica ativa (CLAUDE.md §3 — primeira etapa, em MySQL).
 * - Filtros de cadastro (nome/responsável/telefone/microchip, espécie, raça, sexo, castração, idade) para quem lê pacientes.
 * - Filtros CLÍNICOS (diagnóstico + situação, ano de atendimento) só para quem tem `clinical:read`; para os demais são
 *   ignorados no servidor, não apenas escondidos na tela.
 * - Só atendimentos FINALIZADOS contam: rascunho ainda pode mudar e não é registro clínico confiável.
 * - Suspeita, confirmado e descartado nunca se misturam: o resultado mostra a situação de cada diagnóstico encontrado.
 * Toda consulta passa pelo escopo da clínica (TenantScope); subconsultas também são modelos com escopo.
 */
final class PatientSearch
{
    /** Situação do diagnóstico: '' = suspeita ou confirmado (descartado só quando pedido explicitamente). */
    public const DX_STATUS = ['' => 'Suspeita ou confirmado', 'confirmed' => 'Somente confirmado', 'suspected' => 'Somente suspeita', 'ruled_out' => 'Somente descartado'];

    public const MAX_AGE = 80;

    /** Fuso usado para o "ano de atendimento" (padrão das unidades; ver ADR de fuso por unidade). */
    private const TZ = 'America/Sao_Paulo';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Normaliza a query string; valores inválidos são descartados (filtro desligado), nunca repassados ao SQL.
     *
     * @return array{q: string, especie: string, raca: string, sexo: string, castrado: string, idade_min: ?int, idade_max: ?int, diagnostico: string, situacao: string, ano: ?int, todos: bool}
     */
    public function filters(Request $request): array
    {
        $str = fn (string $k, int $max = 120): string => mb_substr(trim((string) $request->query($k, '')), 0, $max);
        $int = function (string $k, int $min, int $max) use ($request): ?int {
            $v = $request->query($k);

            return is_string($v) && ctype_digit($v) && (int) $v >= $min && (int) $v <= $max ? (int) $v : null;
        };
        $clinical = $this->context->can('clinical:read');
        $f = [
            'q' => $str('q'),
            'especie' => in_array($request->query('especie'), Species::codes(), true) ? (string) $request->query('especie') : '',
            'raca' => $str('raca', 60),
            'sexo' => array_key_exists((string) $request->query('sexo'), Patient::SEX) ? (string) $request->query('sexo') : '',
            'castrado' => array_key_exists((string) $request->query('castrado'), Patient::NEUTERED) ? (string) $request->query('castrado') : '',
            'idade_min' => $int('idade_min', 0, self::MAX_AGE),
            'idade_max' => $int('idade_max', 0, self::MAX_AGE),
            'diagnostico' => $clinical ? $str('diagnostico', 100) : '',
            'situacao' => $clinical && array_key_exists((string) $request->query('situacao'), self::DX_STATUS) ? (string) $request->query('situacao') : '',
            'ano' => $clinical ? $int('ano', 1990, (int) now()->year) : null,
            'todos' => $request->boolean('todos'),
        ];
        if ($f['idade_min'] !== null && $f['idade_max'] !== null && $f['idade_min'] > $f['idade_max']) {
            [$f['idade_min'], $f['idade_max']] = [$f['idade_max'], $f['idade_min']];
        }

        return $f;
    }

    /** @param array<string, mixed> $f */
    public static function hasDiagnosisFilter(array $f): bool
    {
        return $f['diagnostico'] !== '' || $f['situacao'] !== '';
    }

    /** @param array<string, mixed> $f */
    public static function hasClinicalFilter(array $f): bool
    {
        return self::hasDiagnosisFilter($f) || $f['ano'] !== null;
    }

    /** @param array<string, mixed> $f */
    public static function hasAdvancedFilter(array $f): bool
    {
        return $f['raca'] !== '' || $f['sexo'] !== '' || $f['castrado'] !== '' || $f['idade_min'] !== null || $f['idade_max'] !== null || self::hasClinicalFilter($f);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Patient>
     */
    public function query(array $f): Builder
    {
        $q = (string) $f['q'];

        return Patient::query()
            ->when($q !== '', function (Builder $query) use ($q) {
                // Em 2 passos (sem EXISTS correlacionado): responsáveis que casam com a busca → seus pacientes.
                $guardianIds = GuardianController::search(Guardian::query(), $q)->limit(500)->pluck('id')->all();
                $like = '%'.addcslashes($q, '%_\\').'%';
                $digits = BrazilianDocument::digits($q);
                $tenantId = $this->context->requireId();
                $query->where(function ($w) use ($q, $like, $digits, $guardianIds, $tenantId) {
                    $w->where('name', 'like', $like)
                        ->orWhere('microchip', strtoupper(str_replace([' ', '.', '-'], '', $q)));
                    if (strlen($digits) >= 4) {
                        $w->orWhere('microchip', 'like', '%'.$digits.'%');
                    }
                    if ($guardianIds !== []) {
                        $w->orWhereIn('id', DB::table('guardian_patient')->select('patient_id')
                            ->where('tenant_id', $tenantId)->whereIn('guardian_id', $guardianIds));
                    }
                });
            })
            ->when($f['especie'] !== '', fn (Builder $query) => $query->where('species', $f['especie']))
            ->when($f['raca'] !== '', fn (Builder $query) => $query->where('breed', 'like', '%'.addcslashes((string) $f['raca'], '%_\\').'%'))
            ->when($f['sexo'] !== '', fn (Builder $query) => $query->where('sex', $f['sexo']))
            ->when($f['castrado'] !== '', fn (Builder $query) => $query->where('neutered', $f['castrado']))
            // Idade em anos completos hoje: N anos ⇔ nascido entre (hoje − N−1 anos, hoje − N anos].
            ->when($f['idade_min'] !== null, fn (Builder $query) => $query->where('birth_date', '<=', now()->subYears((int) $f['idade_min'])->toDateString()))
            ->when($f['idade_max'] !== null, fn (Builder $query) => $query->where('birth_date', '>', now()->subYears((int) $f['idade_max'] + 1)->toDateString()))
            ->when(self::hasDiagnosisFilter($f), fn (Builder $query) => $query->whereIn('id', $this->diagnoses($f)->select('patient_id')))
            ->when(! self::hasDiagnosisFilter($f) && $f['ano'] !== null, fn (Builder $query) => $query->whereIn('id', $this->encounters($f)->select('patient_id')))
            ->when(! $f['todos'], fn (Builder $query) => $query->where('status', 'active'));
    }

    /**
     * Evidência: quais diagnósticos coincidiram, em que atendimento e data (até 3 por paciente, mais recentes primeiro).
     *
     * @param  array<string, mixed>  $f
     * @param  list<string>  $patientIds
     * @return array<string, list<array{description: string, code: ?string, status: string, encounter_id: string, date: Carbon}>>
     */
    public function evidence(array $f, array $patientIds): array
    {
        if (! self::hasDiagnosisFilter($f) || $patientIds === []) {
            return [];
        }
        $dates = $this->encounters($f)->whereIn('patient_id', $patientIds)->pluck('started_at', 'id');
        $out = [];
        foreach ($this->diagnoses($f)->whereIn('patient_id', $patientIds)->get(['patient_id', 'encounter_id', 'description', 'code', 'status']) as $d) {
            $out[$d->patient_id][] = ['description' => $d->description, 'code' => $d->code, 'status' => $d->status, 'encounter_id' => $d->encounter_id, 'date' => Carbon::parse($dates[$d->encounter_id])];
        }
        foreach ($out as $id => $rows) {
            usort($rows, fn ($a, $b) => $b['date'] <=> $a['date']);
            $out[$id] = array_slice($rows, 0, 3);
        }

        return $out;
    }

    /**
     * Anos com atendimentos finalizados (para o seletor), do mais recente ao mais antigo.
     *
     * @return list<int>
     */
    public function encounterYears(): array
    {
        $min = Encounter::query()->where('status', 'finalized')->min('started_at');
        $first = $min ? (int) Carbon::parse($min)->timezone(self::TZ)->year : (int) now()->year;

        return range((int) now()->year, $first);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Encounter>
     */
    private function encounters(array $f): Builder
    {
        return Encounter::query()->where('status', 'finalized')
            ->when($f['ano'] !== null, fn (Builder $q) => $q->whereBetween('started_at', [
                Carbon::create((int) $f['ano'], 1, 1, 0, 0, 0, self::TZ)->utc(),
                Carbon::create((int) $f['ano'], 12, 31, 23, 59, 59, self::TZ)->utc(),
            ]));
    }

    /**
     * Diagnósticos de atendimentos finalizados que casam com o texto (descrição ou código) e a situação.
     * Collation utf8mb4_unicode_ci: "atopica" encontra "atópica" e maiúsculas/minúsculas não importam.
     *
     * @param  array<string, mixed>  $f
     * @return Builder<EncounterDiagnosis>
     */
    private function diagnoses(array $f): Builder
    {
        $term = (string) $f['diagnostico'];

        return EncounterDiagnosis::query()
            ->when($term !== '', fn (Builder $q) => $q->where(fn ($w) => $w->where('description', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('code', $term)))
            ->when($f['situacao'] === '', fn (Builder $q) => $q->whereIn('status', ['suspected', 'confirmed']), fn (Builder $q) => $q->where('status', $f['situacao']))
            ->whereIn('encounter_id', $this->encounters($f)->select('id'));
    }
}
