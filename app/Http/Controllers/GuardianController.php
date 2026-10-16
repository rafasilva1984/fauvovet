<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Services\PatientRegistry;
use App\Support\BrazilianDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Responsáveis (tutores) da clínica ativa. Route model binding escopado: responsável de outra clínica → 404. */
final class GuardianController extends Controller
{
    public function __construct(private readonly PatientRegistry $registry) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $guardians = Guardian::query()
            ->when($q !== '', fn ($query) => self::search($query, $q))
            ->when(! $request->boolean('arquivados'), fn ($query) => $query->where('status', 'active'))
            ->withCount('patients')
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('guardians.index', ['guardians' => $guardians, 'q' => $q]);
    }

    public function create(): View
    {
        return view('guardians.create', ['guardian' => new Guardian(['person_type' => 'PF'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->registry->guardianRules($request->all()), $this->registry->guardianMessages());
        $attrs = $this->registry->guardianAttributes($data);
        if (! $request->boolean('confirm_duplicate') && ($dups = $this->registry->guardianDuplicates($attrs))->isNotEmpty()) {
            return back()->withInput()->with('duplicates', $dups->map(fn (Guardian $g) => ['id' => $g->id, 'name' => $g->name])->all());
        }
        $guardian = $this->registry->createGuardian($attrs, (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('guardians.show', $guardian)->with('status', 'Responsável cadastrado.');
    }

    public function show(Guardian $guardian): View
    {
        $guardian->load(['patients' => fn ($q) => $q->orderBy('name')]);

        return view('guardians.show', ['guardian' => $guardian]);
    }

    public function edit(Guardian $guardian): View
    {
        return view('guardians.edit', ['guardian' => $guardian]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $request->validate([
            ...$this->registry->guardianRules($request->all()),
            'status' => ['required', Rule::in(['active', 'archived'])],
        ], $this->registry->guardianMessages());
        $attrs = $this->registry->guardianAttributes($data);
        if (! $request->boolean('confirm_duplicate') && ($dups = $this->registry->guardianDuplicates($attrs, $guardian->id))->isNotEmpty()) {
            return back()->withInput()->with('duplicates', $dups->map(fn (Guardian $g) => ['id' => $g->id, 'name' => $g->name])->all());
        }
        $this->registry->updateGuardian($guardian, $attrs, $data['status'], (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('guardians.show', $guardian)->with('status', 'Responsável atualizado.');
    }

    /**
     * Busca por nome, e-mail ou dígitos (telefone/CPF/CNPJ). Sempre dentro da clínica (escopo do model).
     *
     * @param  Builder<Guardian>  $query
     * @return Builder<Guardian>
     */
    public static function search($query, string $q)
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $digits = BrazilianDocument::digits($q);

        return $query->where(function ($w) use ($like, $digits) {
            $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            if (strlen($digits) >= 4) {
                $d = '%'.$digits.'%';
                $w->orWhere('phone', 'like', $d)->orWhere('phone_alt', 'like', $d)->orWhere('document_number', 'like', $d);
            }
        });
    }
}
