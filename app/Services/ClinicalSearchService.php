<?php

namespace App\Services;

use App\Models\EncounterDiagnosis;
use App\Models\User;
use App\Support\SearchText;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Busca clínica em texto livre (CLAUDE.md §3; ADR-017) — contrato independente do mecanismo de índice.
 * Semântica por PACIENTE: numa alternativa (separada por OU), cada termo/frase precisa aparecer em algum registro
 * do paciente (pode cruzar campos: "penicilina antibiotico" acha alerta de alergia + conduta). NÃO exclui pacientes
 * cujo registro (no recorte dos filtros) contenha o termo. Cada resultado traz a evidência: origem, data e trecho.
 */
final class ClinicalSearchService
{
    public const PER_PAGE = 20;

    /** Teto de registros por termo — protege contra consultas caras; acima disso pede para refinar. */
    public const MAX_HITS = 5000;

    public const SOURCES = ['encounter' => 'Atendimento', 'diagnosis' => 'Diagnóstico', 'addendum' => 'Adendo', 'alert' => 'Alerta', 'document' => 'Documento emitido', 'vaccination' => 'Vacina'];

    public const PERIODS = ['30' => 'Últimos 30 dias', '180' => 'Últimos 6 meses', '365' => 'Últimos 12 meses', '730' => 'Últimos 2 anos', '1825' => 'Últimos 5 anos'];

    public const SORTS = ['relevancia' => 'Relevância', 'recentes' => 'Mais recentes', 'antigos' => 'Mais antigos', 'nome' => 'Nome do paciente'];

    private const WEIGHT = ['diagnosis' => 3, 'encounter' => 2, 'addendum' => 2, 'alert' => 2, 'document' => 1, 'vaccination' => 1];

    public function __construct(private readonly TenantContext $context) {}

    /** @return array{q: string, especie: string, periodo: string, origem: string, situacao: string, ordem: string, pagina: int} */
    public static function filters(Request $request): array
    {
        $in = fn (string $key, array $allowed) => in_array($v = (string) $request->query($key, ''), $allowed, true) ? $v : '';

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 200),
            'especie' => $in('especie', Species::codes()),
            'periodo' => $in('periodo', array_map('strval', array_keys(self::PERIODS))),
            'origem' => $in('origem', array_keys(self::SOURCES)),
            'situacao' => $in('situacao', array_keys(EncounterDiagnosis::STATUS)),
            'ordem' => $in('ordem', array_keys(self::SORTS)) ?: 'relevancia',
            'pagina' => max(1, min(500, (int) $request->query('pagina', 1))),
        ];
    }

    /**
     * @param  array{q: string, especie: string, periodo: string, origem: string, situacao: string, ordem: string, pagina: int}  $f
     * @return array{parsed: array{groups: list<array{terms: list<string>, phrases: list<string>}>, exclude: list<string>, ignored: list<string>}, total: int, hits: int, truncated: bool, results: list<array<string, mixed>>, pages: int}
     */
    public function search(array $f): array
    {
        $parsed = SearchText::parse($f['q']);
        $empty = ['parsed' => $parsed, 'total' => 0, 'hits' => 0, 'truncated' => false, 'results' => [], 'pages' => 0];
        if ($parsed['groups'] === []) {
            return $empty;
        }
        $truncated = false;
        $match = []; // item => [doc_id => patient_id]
        foreach ($parsed['groups'] as $g) {
            foreach ($g['terms'] as $t) {
                $match['t:'.$t] ??= $this->termDocs($t, $f, $truncated);
            }
            foreach ($g['phrases'] as $p) {
                $match['p:'.$p] ??= $this->phraseDocs($p, $f, $truncated);
            }
        }
        $excluded = [];
        foreach ($parsed['exclude'] as $x) {
            $docs = str_starts_with($x, '"') ? $this->phraseDocs(trim($x, '"'), $f, $truncated) : $this->termDocs($x, $f, $truncated);
            $excluded += array_flip(array_values($docs));
        }

        $patients = [];
        foreach ($parsed['groups'] as $g) {
            $items = [...array_map(fn ($t) => 't:'.$t, $g['terms']), ...array_map(fn ($p) => 'p:'.$p, $g['phrases'])];
            $set = null;
            foreach ($items as $item) {
                $ids = array_flip(array_values($match[$item]));
                $set = $set === null ? $ids : array_intersect_key($set, $ids);
            }
            $patients += $set ?? [];
        }
        $patients = array_diff_key($patients, $excluded);
        if ($patients === []) {
            return ['truncated' => $truncated] + $empty;
        }

        // Evidências: registros que casaram algum item, só dos pacientes do resultado.
        $docItems = []; // doc_id => list<item>
        foreach ($match as $item => $docs) {
            foreach ($docs as $docId => $patientId) {
                if (isset($patients[$patientId])) {
                    $docItems[$docId][] = $item;
                }
            }
        }
        $docs = $this->scoped()->whereIn('d.id', array_keys($docItems))
            ->get(['d.id', 'd.patient_id', 'd.encounter_id', 'd.source_type', 'd.source_id', 'd.label', 'd.dx_status', 'd.occurred_at', 'd.author_id', 'd.body']);
        $byPatient = $docs->groupBy('patient_id');
        $info = DB::table('patients')->where('tenant_id', $this->context->requireId())->whereIn('id', array_keys($patients))
            ->get(['id', 'name', 'species', 'species_detail', 'breed', 'status'])->keyBy('id');

        $ranked = collect(array_keys($patients))->map(function ($pid) use ($byPatient, $docItems, $info) {
            $list = $byPatient->get($pid, collect());

            return [
                'id' => $pid, 'patient' => $info[$pid] ?? null,
                'score' => $list->sum(fn ($d) => (self::WEIGHT[$d->source_type] ?? 1) * count(array_unique($docItems[$d->id] ?? []))),
                'latest' => (string) $list->max('occurred_at'), 'oldest' => (string) $list->min('occurred_at'), 'docs' => $list,
            ];
        })->filter(fn ($r) => $r['patient'] !== null);
        $ranked = match ($f['ordem']) {
            'recentes' => $ranked->sortByDesc('latest'),
            'antigos' => $ranked->sortBy('oldest'),
            'nome' => $ranked->sortBy(fn ($r) => mb_strtolower($r['patient']->name)),
            default => $ranked->sortBy([['score', 'desc'], ['latest', 'desc']]),
        };

        $total = $ranked->count();
        $page = $ranked->values()->slice(($f['pagina'] - 1) * self::PER_PAGE, self::PER_PAGE);
        $authors = User::query()->whereIn('id', $page->flatMap(fn ($r) => $r['docs']->pluck('author_id'))->filter()->unique()->all())->pluck('name', 'id');
        $results = $page->map(function ($r) use ($docItems, $authors) {
            $evidence = $r['docs']->sortByDesc('occurred_at')->values()->map(function ($d) use ($docItems, $authors) {
                $items = $docItems[$d->id] ?? [];

                return [
                    'source' => $d->source_type, 'label' => $d->label, 'dx_status' => $d->dx_status, 'encounter_id' => $d->encounter_id,
                    'source_id' => $d->source_id, 'when' => Carbon::parse($d->occurred_at, 'UTC')->timezone('America/Sao_Paulo'),
                    'author' => $d->author_id ? ($authors[$d->author_id] ?? null) : null,
                    'snippet' => SearchText::snippet((string) $d->body, $this->itemsOf($items, 't:'), $this->itemsOf($items, 'p:')),
                ];
            });

            return ['patient' => $r['patient'], 'evidence' => $evidence->take(3)->all(), 'more' => max(0, $evidence->count() - 3), 'hits' => $evidence->count()];
        })->values()->all();

        return ['parsed' => $parsed, 'total' => $total, 'hits' => $docs->count(), 'truncated' => $truncated, 'results' => $results, 'pages' => (int) ceil($total / self::PER_PAGE)];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function itemsOf(array $items, string $prefix): array
    {
        return array_values(array_map(fn ($i) => substr($i, 2), array_filter($items, fn ($i) => str_starts_with($i, $prefix))));
    }

    /**
     * Documentos do índice da clínica ativa, já com os filtros de recorte (espécie, período, origem, situação).
     *
     * @param  array{q: string, especie: string, periodo: string, origem: string, situacao: string, ordem: string, pagina: int}|null  $f
     */
    private function scoped(?array $f = null): Builder
    {
        $tenant = $this->context->requireId();
        $q = DB::table('clinical_search_documents as d')->where('d.tenant_id', $tenant);
        if ($f === null) {
            return $q;
        }
        if ($f['especie'] !== '') {
            $q->join('patients as p', fn ($j) => $j->on('p.id', '=', 'd.patient_id')->on('p.tenant_id', '=', 'd.tenant_id'))->where('p.species', $f['especie']);
        }
        if ($f['periodo'] !== '') {
            $q->where('d.occurred_at', '>=', now('America/Sao_Paulo')->subDays((int) $f['periodo'])->startOfDay()->utc());
        }
        if ($f['situacao'] !== '') {
            $q->where('d.source_type', 'diagnosis')->where('d.dx_status', $f['situacao']);
        } elseif ($f['origem'] !== '') {
            $q->where('d.source_type', $f['origem']);
        }

        return $q;
    }

    /**
     * @param  array{q: string, especie: string, periodo: string, origem: string, situacao: string, ordem: string, pagina: int}  $f
     * @return array<string, string> doc_id => patient_id
     */
    private function termDocs(string $term, array $f, bool &$truncated): array
    {
        $rows = $this->scoped($f)
            ->join('clinical_search_terms as t', fn ($j) => $j->on('t.document_id', '=', 'd.id')->on('t.tenant_id', '=', 'd.tenant_id'))
            ->where('t.term', 'like', $term.'%') // termo só tem [a-z0-9]: sem curingas do usuário
            ->distinct()->limit(self::MAX_HITS + 1)->pluck('d.patient_id', 'd.id')->all();
        if (count($rows) > self::MAX_HITS) {
            $truncated = true;
            $rows = array_slice($rows, 0, self::MAX_HITS, true);
        }

        return $rows;
    }

    /**
     * @param  array{q: string, especie: string, periodo: string, origem: string, situacao: string, ordem: string, pagina: int}  $f
     * @return array<string, string> doc_id => patient_id
     */
    private function phraseDocs(string $phrase, array $f, bool &$truncated): array
    {
        // Pré-filtro por LIKE (palavras na ordem) e confirmação exata em PHP (pontuação entre as palavras é aceita).
        $rows = $this->scoped($f)->where('d.normalized', 'like', '%'.str_replace(' ', '%', $phrase).'%')
            ->limit(self::MAX_HITS + 1)->get(['d.id', 'd.patient_id', 'd.normalized']);
        if ($rows->count() > self::MAX_HITS) {
            $truncated = true;
        }
        $pattern = '/(?<![a-z0-9])'.str_replace(' ', '\s+', preg_quote($phrase, '/')).'(?![a-z0-9])/';

        return $rows->take(self::MAX_HITS)->filter(fn ($r) => preg_match($pattern, (string) $r->normalized) === 1)->pluck('patient_id', 'id')->all();
    }
}
