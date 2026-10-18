<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use App\Services\ClinicalSearchService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pesquisas salvas da busca clínica: privadas de quem salvou ou compartilhadas com a clínica. */
final class SavedSearchController extends Controller
{
    public function __construct(private readonly Audit $audit, private readonly TenantContext $context) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80'], 'shared' => ['nullable', 'boolean']], [], ['name' => 'nome da pesquisa']);
        $f = ClinicalSearchService::filters($request);
        abort_if($f['q'] === '', 422);
        $params = array_filter(array_intersect_key($f, array_flip(SavedSearch::PARAMS)), fn ($v) => $v !== '' && $v !== 'relevancia');
        $s = SavedSearch::create(['user_id' => (string) $request->user()?->getAuthIdentifier(), 'name' => $data['name'], 'params' => $params, 'shared' => $request->boolean('shared')]);
        $this->audit->record('saved_search.created', (string) $request->user()?->getAuthIdentifier(), 'saved_search', $s->id, ['shared' => $s->shared]);

        return redirect()->route('search.index', $params)->with('status', "Pesquisa “{$s->name}” salva.");
    }

    public function destroy(Request $request, SavedSearch $search): RedirectResponse
    {
        $mine = $search->user_id === (string) $request->user()?->getAuthIdentifier();
        abort_unless($mine || in_array('tenant_owner', $this->context->roles(), true), 403);
        $search->delete();
        $this->audit->record('saved_search.deleted', (string) $request->user()?->getAuthIdentifier(), 'saved_search', $search->id);

        return redirect()->route('search.index')->with('status', 'Pesquisa salva excluída.');
    }
}
