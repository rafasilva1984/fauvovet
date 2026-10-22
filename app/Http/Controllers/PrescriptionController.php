<?php

namespace App\Http\Controllers;

use App\Models\DocumentBackground;
use App\Models\Encounter;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\User;
use App\Services\DocumentBackgroundService;
use App\Services\DocumentService;
use App\Services\PrescriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Receita estruturada: o veterinário monta os itens, o servidor calcula a dose pelo peso e mostra os avisos;
 * "Calcular e revisar" não grava nada; "Emitir" gera o PDF sobre o receituário e grava os itens (ADR-022).
 */
final class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $prescriptions, private readonly DocumentService $documents) {}

    public function create(Request $request, Patient $patient, DocumentBackgroundService $backgrounds): View
    {
        if (! $this->documents->canIssue('prescription')) {
            abort(403);
        }
        $encounter = $this->encounter($patient, (string) $request->query('atendimento', old('encounter_id', '')));
        $weight = PrescriptionService::decimal(old('weight_kg')) ?? $encounter->weight_kg ?? $this->prescriptions->latestWeight($patient);
        $rows = array_values(array_filter((array) old('rows', []), 'is_array'));
        $plan = $request->session()->has('rx_preview') ? $this->prescriptions->plan($patient, $rows, $weight !== null ? (float) $weight : null) : null;
        $filled = count(array_filter($rows, fn ($r) => trim((string) ($r['medication_id'] ?? '')) !== '' || trim((string) ($r['name'] ?? '')) !== ''));
        $user = $this->user($request);

        return view('prescriptions.create', [
            'patient' => $patient,
            'encounter' => $encounter,
            'medications' => $this->prescriptions->catalog($patient),
            'weight' => $weight !== null ? (float) $weight : null,
            'rows' => array_pad($rows, min(PrescriptionService::MAX_ROWS, max(3, $filled + 1)), []),
            'plan' => $plan,
            'body' => $plan && $plan['errors'] === [] ? $this->prescriptions->body($patient, $plan['items'], $weight !== null ? (float) $weight : null, old('guidance')) : null,
            'allergies' => PatientAlert::query()->where('patient_id', $patient->id)->where('kind', 'allergy')->whereNull('resolved_at')->get(),
            'current' => $this->prescriptions->current($patient),
            'backgrounds' => $backgrounds->current(),
            'needsCrmv' => trim((string) $user->crmv) === '',
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        if (! $this->documents->canIssue('prescription')) {
            abort(403);
        }
        abort_unless($patient->status === 'active', 422, 'Paciente inativo.');
        if (is_string($request->input('weight_kg'))) {
            $request->merge(['weight_kg' => str_replace(',', '.', trim($request->input('weight_kg')))]);
        }
        $data = $request->validate([
            'encounter_id' => ['nullable', 'string', 'size:26'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:2000'],
            'guidance' => ['nullable', 'string', 'max:3000'],
            'background' => ['nullable', Rule::in(array_keys(DocumentBackground::CHOICES))],
            'action' => ['required', Rule::in(['preview', 'issue'])],
            'confirm_warnings' => ['nullable', 'boolean'],
            'rows' => ['nullable', 'array', 'max:'.PrescriptionService::MAX_ROWS],
            'rows.*.medication_id' => ['nullable', 'string', 'size:26'],
            'rows.*.name' => ['nullable', 'string', 'max:160'],
            'rows.*.presentation' => ['nullable', 'string', 'max:160'],
            'rows.*.dose_mg_kg' => ['nullable', 'string', 'max:12', 'regex:/^\d{1,6}([.,]\d{1,4})?$/'],
            'rows.*.dose_text' => ['nullable', 'string', 'max:160'],
            'rows.*.dose_auto' => ['nullable', 'string', 'max:160'],
            'rows.*.route' => ['nullable', Rule::in(Medication::ROUTES)],
            'rows.*.frequency' => ['nullable', Rule::in(Medication::FREQUENCIES)],
            'rows.*.duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'rows.*.continuous' => ['nullable', 'boolean'],
            'rows.*.instructions' => ['nullable', 'string', 'max:500'],
        ], [
            'weight_kg.max' => 'Peso acima de 2.000 kg: confira o valor.',
            'rows.*.dose_mg_kg.regex' => 'Dose por kg inválida (use números, ex.: 12,5).',
        ]);
        $encounter = $this->encounter($patient, (string) ($data['encounter_id'] ?? ''));
        if (($data['encounter_id'] ?? '') !== '' && $encounter === null) {
            abort(404);
        }
        $rows = array_values($data['rows'] ?? []);
        foreach ($rows as $row) {
            // Medicamento do catálogo de OUTRA clínica (ou inexistente) é recusado, não ignorado.
            if (($row['medication_id'] ?? '') !== '' && ! Medication::query()->whereKey($row['medication_id'])->exists()) {
                abort(404);
            }
        }
        $weight = isset($data['weight_kg']) ? (float) $data['weight_kg']
            : (! $request->has('weight_kg') ? ($encounter?->weight_kg !== null ? (float) $encounter->weight_kg : $this->prescriptions->latestWeight($patient)) : null);
        $plan = $this->prescriptions->plan($patient, $rows, $weight);
        $back = redirect()->route('prescriptions.create', array_filter(['patient' => $patient, 'atendimento' => $encounter?->id]))
            ->withInput([...$request->except(['action', 'confirm_warnings']), 'weight_kg' => $weight !== null ? Medication::num($weight, 3) : '', 'rows' => $this->filledRows($rows, $plan['items'])])->with('rx_preview', true);

        if ($data['action'] === 'preview' || $plan['errors'] !== []) {
            return $data['action'] === 'issue' ? $back->withErrors($plan['errors']) : $back;
        }
        if ($plan['warnings'] !== [] && ! $request->boolean('confirm_warnings')) {
            return $back->withErrors(['confirm_warnings' => 'Confira os avisos e marque que revisou antes de emitir.']);
        }

        try {
            $document = $this->prescriptions->issue($patient, $encounter, $plan['items'], $weight, $data['guidance'] ?? null, $this->user($request), $data['background'] ?? 'auto');
        } catch (ValidationException $e) {
            return $back->withErrors(['rows' => collect($e->errors())->flatten()->first()]);
        }

        return redirect()->route('documents.show', $document)->with('status', 'Receita emitida. O PDF foi gerado sobre o receituário e os medicamentos ficaram registrados.');
    }

    /**
     * Devolve ao formulário o que o servidor completou (dose calculada, via/frequência/duração do catálogo), para o
     * veterinário ver e ajustar exatamente o que será emitido.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filledRows(array $rows, array $items): array
    {
        foreach ($items as $item) {
            $i = $item['row'];
            $rows[$i] = [...$rows[$i],
                'dose_text' => $item['dose_text'], 'dose_auto' => $item['from_calc'] ? $item['dose_text'] : '', 'route' => $item['route'], 'frequency' => $item['frequency'],
                'duration_days' => $item['duration_days'], 'presentation' => $item['presentation'],
                'dose_mg_kg' => ($rows[$i]['dose_mg_kg'] ?? '') !== '' ? $rows[$i]['dose_mg_kg'] : ($item['medication']?->dose_mg_kg !== null ? Medication::num((float) $item['medication']->dose_mg_kg) : ''),
            ];
        }

        return $rows;
    }

    private function encounter(Patient $patient, string $id): ?Encounter
    {
        return $id === '' ? null : Encounter::query()->where('patient_id', $patient->id)->find($id);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
