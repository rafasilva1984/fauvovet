<?php

namespace App\Http\Controllers;

use App\Models\Vaccine;
use App\Services\VaccinationService;
use App\Support\Audit;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catálogo de vacinas da clínica (protocolos). Desativar em vez de apagar: há aplicações registradas. */
final class VaccineController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function index(VaccinationService $service): View
    {
        $service->ensureDefaults();

        return view('vaccinations.catalog', ['vaccines' => Vaccine::query()->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('vaccinations.vaccine-form', ['vaccine' => new Vaccine(['species' => [], 'series_doses' => 1, 'series_interval_days' => 21, 'booster_interval_days' => 365, 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = Vaccine::create([...$this->validated($request), 'active' => true]);
        $this->audit->record('vaccine.created', (string) $request->user()?->getAuthIdentifier(), 'vaccine', $v->id);

        return redirect()->route('vaccines.index')->with('status', "Vacina “{$v->name}” cadastrada.");
    }

    public function edit(Vaccine $vaccine): View
    {
        return view('vaccinations.vaccine-form', ['vaccine' => $vaccine]);
    }

    public function update(Request $request, Vaccine $vaccine): RedirectResponse
    {
        $vaccine->fill([...$this->validated($request, $vaccine), 'active' => $request->boolean('active')])->save();
        $this->audit->record('vaccine.updated', (string) $request->user()?->getAuthIdentifier(), 'vaccine', $vaccine->id);

        return redirect()->route('vaccines.index')->with('status', "Vacina “{$vaccine->name}” salva. Aplicações já registradas não mudam.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Vaccine $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('vaccines', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'species' => ['nullable', 'array'],
            'species.*' => [Rule::in(Species::codes())],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'series_doses' => ['required', 'integer', 'min:1', 'max:10'],
            'series_interval_days' => ['required', 'integer', 'min:0', 'max:365'],
            'booster_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], ['name.unique' => 'Já existe uma vacina com este nome.']);

        return [...$data, 'species' => array_values(array_unique($data['species'] ?? []))];
    }
}
