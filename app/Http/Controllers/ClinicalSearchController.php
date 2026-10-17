<?php

namespace App\Http\Controllers;

use App\Services\ClinicalSearchIndex;
use App\Services\ClinicalSearchService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Busca clínica em texto livre (clinical:read). O índice é construído na primeira busca da clínica (ou quando a
 * regra de normalização muda) e mantido em dia pelos serviços do prontuário. Auditoria sem o termo pesquisado.
 */
final class ClinicalSearchController extends Controller
{
    public function __construct(private readonly ClinicalSearchService $search, private readonly ClinicalSearchIndex $index, private readonly Audit $audit) {}

    public function index(Request $request): View
    {
        $built = false;
        if (! $this->index->isCurrent()) {
            $this->index->rebuild();
            $built = true;
        }
        $f = ClinicalSearchService::filters($request);
        $result = $f['q'] !== '' ? $this->search->search($f) : null;
        if ($result !== null) {
            $this->audit->record('clinical.search', (string) $request->user()?->getAuthIdentifier(), null, null, [
                'filters' => array_keys(array_filter(['especie' => $f['especie'], 'periodo' => $f['periodo'], 'origem' => $f['origem'], 'situacao' => $f['situacao']])),
                'alternatives' => count($result['parsed']['groups']), 'excluded' => count($result['parsed']['exclude']), 'patients' => $result['total'],
            ]);
        }

        return view('search.index', ['f' => $f, 'result' => $result, 'status' => $this->index->status(), 'built' => $built]);
    }

    public function rebuild(Request $request): RedirectResponse
    {
        $count = $this->index->rebuild();
        $this->audit->record('clinical.search_reindexed', (string) $request->user()?->getAuthIdentifier(), null, null, ['documents' => $count]);

        return redirect()->route('search.index')->with('status', "Índice da busca clínica reconstruído: {$count} trechos.");
    }
}
