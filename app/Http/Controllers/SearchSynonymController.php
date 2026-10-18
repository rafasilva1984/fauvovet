<?php

namespace App\Http\Controllers;

use App\Models\ClinicalSearchSynonym;
use App\Support\Audit;
use App\Support\SearchText;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Dicionário de sinônimos da busca clínica (search:configure: titular e veterinário). Vale na hora, sem reindexar. */
final class SearchSynonymController extends Controller
{
    public function __construct(private readonly Audit $audit) {}

    public function index(): View
    {
        return view('search.synonyms', ['groups' => ClinicalSearchSynonym::query()->orderBy('terms')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $g = ClinicalSearchSynonym::create(['terms' => $this->validated($request), 'created_by' => (string) $request->user()?->getAuthIdentifier()]);
        $this->audit->record('search_synonym.created', (string) $request->user()?->getAuthIdentifier(), 'search_synonym', $g->id);

        return redirect()->route('search.synonyms')->with('status', 'Grupo de sinônimos criado.');
    }

    public function update(Request $request, ClinicalSearchSynonym $synonym): RedirectResponse
    {
        $synonym->fill(['terms' => $this->validated($request, 'terms_'.$synonym->id), 'updated_by' => (string) $request->user()?->getAuthIdentifier()])->save();
        $this->audit->record('search_synonym.updated', (string) $request->user()?->getAuthIdentifier(), 'search_synonym', $synonym->id);

        return redirect()->route('search.synonyms')->with('status', 'Grupo de sinônimos salvo.');
    }

    public function destroy(Request $request, ClinicalSearchSynonym $synonym): RedirectResponse
    {
        $synonym->delete();
        $this->audit->record('search_synonym.deleted', (string) $request->user()?->getAuthIdentifier(), 'search_synonym', $synonym->id);

        return redirect()->route('search.synonyms')->with('status', 'Grupo de sinônimos excluído.');
    }

    private function validated(Request $request, string $field = 'terms'): string
    {
        $request->validate([$field => ['required', 'string', 'max:1000', function (string $attr, mixed $value, Closure $fail) {
            if (count(SearchText::synonymVariants((string) $value)) < 2) {
                $fail('Informe pelo menos duas palavras ou expressões diferentes, uma por linha.');
            }
        }]], [], [$field => 'sinônimos']);
        $lines = array_values(array_unique(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 80), preg_split('/[\n;,]+/', (string) $request->input($field)) ?: []))));

        return implode("\n", $lines);
    }
}
