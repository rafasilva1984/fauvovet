<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Support\Audit;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catálogo de medicamentos da clínica (medications:configure). Desativar em vez de apagar: há receitas emitidas. */
final class MedicationController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function index(): View
    {
        return view('prescriptions.catalog', ['medications' => Medication::query()->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('prescriptions.medication-form', ['medication' => new Medication(['species' => [], 'form' => 'tablet', 'route' => 'Oral', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $m = Medication::create([...$this->validated($request), 'active' => true, 'updated_by' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('medication.created', (string) $request->user()?->getAuthIdentifier(), 'medication', $m->id);

        return redirect()->route('medications.index')->with('status', "Medicamento “{$m->name}” cadastrado.");
    }

    public function edit(Medication $medication): View
    {
        return view('prescriptions.medication-form', ['medication' => $medication]);
    }

    public function update(Request $request, Medication $medication): RedirectResponse
    {
        $medication->fill([...$this->validated($request, $medication), 'active' => $request->boolean('active'), 'updated_by' => $request->user()?->getAuthIdentifier()])->save();
        $this->audit->record('medication.updated', (string) $request->user()?->getAuthIdentifier(), 'medication', $medication->id);

        return redirect()->route('medications.index')->with('status', "Medicamento “{$medication->name}” salvo. Receitas já emitidas não mudam.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Medication $current = null): array
    {
        foreach (['concentration_mg', 'dose_mg_kg', 'dose_min_mg_kg', 'dose_max_mg_kg'] as $f) {
            if (is_string($request->input($f))) {
                $request->merge([$f => str_replace(',', '.', trim($request->input($f)))]);
            }
        }
        $dose = ['nullable', 'numeric', 'gt:0', 'max:100000'];
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('medications', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'active_ingredient' => ['nullable', 'string', 'max:120'],
            'drug_class' => ['nullable', 'string', 'max:120'],
            'form' => ['required', Rule::in(array_keys(Medication::FORMS))],
            'concentration_mg' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'presentation' => ['nullable', 'string', 'max:120'],
            'dose_mg_kg' => $dose, 'dose_min_mg_kg' => $dose, 'dose_max_mg_kg' => [...$dose, 'gte:dose_min_mg_kg'],
            'route' => ['nullable', Rule::in(Medication::ROUTES)],
            'frequency' => ['nullable', Rule::in(Medication::FREQUENCIES)],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'species' => ['nullable', 'array'],
            'species.*' => [Rule::in(Species::codes())],
            'controlled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'name.unique' => 'Já existe um medicamento com este nome.',
            'dose_max_mg_kg.gte' => 'A dose máxima deve ser maior ou igual à mínima.',
            '*.gt' => 'Informe um valor maior que zero.',
        ]);
        if ($data['dose_mg_kg'] ?? null) {
            $usual = (float) $data['dose_mg_kg'];
            if ((($data['dose_max_mg_kg'] ?? null) && $usual > (float) $data['dose_max_mg_kg']) || (($data['dose_min_mg_kg'] ?? null) && $usual < (float) $data['dose_min_mg_kg'])) {
                $request->validate(['dose_mg_kg' => 'prohibited'], ['dose_mg_kg.prohibited' => 'A dose usual está fora da faixa mínima–máxima informada.']);
            }
        }

        return [...$data, 'species' => array_values(array_unique($data['species'] ?? [])), 'controlled' => $request->boolean('controlled')];
    }
}
