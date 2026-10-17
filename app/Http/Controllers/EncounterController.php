<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\EncounterDiagnosis;
use App\Models\IssuedDocument;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\User;
use App\Services\ClinicalRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Atendimento/prontuário. Binding escopado: atendimento de outra clínica → 404. Toda abertura é auditada. */
final class EncounterController extends Controller
{
    public const MUCOUS = ['Normocoradas', 'Hipocoradas', 'Hiperêmicas', 'Ictéricas', 'Cianóticas'];

    public const HYDRATION = ['Normal', 'Desidratação leve (até 5%)', 'Desidratação moderada (6–8%)', 'Desidratação grave (> 8%)'];

    public function __construct(private readonly ClinicalRecordService $records) {}

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->status === 'active', 422, 'Paciente inativo.');
        $encounter = $this->records->start($patient, null, $this->actor($request));

        return redirect()->route('encounters.show', $encounter)->with('status', 'Atendimento iniciado (rascunho).');
    }

    public function show(Request $request, Encounter $encounter): View
    {
        $encounter->load(['patient.guardians:id,name,phone', 'diagnoses', 'addenda.author:id,name', 'author:id,name', 'signer:id,name,crmv', 'unit:id,name,timezone', 'type:id,name', 'appointment']);
        $this->records->recordView('encounter.viewed', 'encounter', $encounter->id, $this->actor($request));

        return view('encounters.show', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'alerts' => PatientAlert::query()->where('patient_id', $encounter->patient_id)->whereNull('resolved_at')->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")->get(),
            'previous' => Encounter::query()->where('patient_id', $encounter->patient_id)->whereKeyNot($encounter->id)
                ->where('status', 'finalized')->with('diagnoses')->orderByDesc('started_at')->limit(3)->get(),
            'documents' => IssuedDocument::query()->where('encounter_id', $encounter->id)->with('issuer:id,name')->orderByDesc('issued_at')->get(),
            'tz' => $encounter->unit->timezone,
            'mucous' => self::MUCOUS,
            'hydration' => self::HYDRATION,
        ]);
    }

    public function update(Request $request, Encounter $encounter): RedirectResponse
    {
        // Aceita vírgula decimal (padrão brasileiro) nos sinais vitais numéricos.
        foreach (['weight_kg', 'temperature_c', 'capillary_refill_s'] as $f) {
            if (is_string($request->input($f))) {
                $request->merge([$f => str_replace(',', '.', trim($request->input($f)))]);
            }
        }
        $text = ['nullable', 'string', 'max:20000'];
        $data = $request->validate([
            'chief_complaint' => $text, 'history' => $text, 'physical_exam' => $text, 'plan' => $text, 'guidance' => $text,
            'weight_kg' => ['nullable', 'numeric', 'min:0.001', 'max:2000'],
            'temperature_c' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'heart_rate' => ['nullable', 'integer', 'min:10', 'max:400'],
            'respiratory_rate' => ['nullable', 'integer', 'min:2', 'max:200'],
            'capillary_refill_s' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'mucous_membranes' => ['nullable', Rule::in(self::MUCOUS)],
            'hydration' => ['nullable', Rule::in(self::HYDRATION)],
            'body_condition_score' => ['nullable', 'integer', 'min:1', 'max:9'],
            'pain_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'diagnoses' => ['nullable', 'array', 'max:20'],
            'diagnoses.*.description' => ['nullable', 'string', 'max:200'],
            'diagnoses.*.code' => ['nullable', 'string', 'max:30'],
            'diagnoses.*.status' => ['nullable', Rule::in(array_keys(EncounterDiagnosis::STATUS))],
            'finalize' => ['nullable', 'boolean'],
            'confirm' => ['nullable', 'boolean'],
        ], [
            'weight_kg.max' => 'Peso acima de 2.000 kg: confira o valor.',
            'temperature_c.min' => 'Temperatura fora da faixa (25–45 °C).', 'temperature_c.max' => 'Temperatura fora da faixa (25–45 °C).',
        ]);
        if ($request->boolean('finalize') && ! $request->boolean('confirm')) {
            throw ValidationException::withMessages(['confirm' => 'Confirme que revisou o registro antes de finalizar.']);
        }

        $diagnoses = [];
        foreach ($data['diagnoses'] ?? [] as $i => $row) {
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                if (trim((string) ($row['code'] ?? '')) !== '') {
                    $request->validate(["diagnoses.{$i}.description" => 'required'], ["diagnoses.{$i}.description.required" => 'Descreva a hipótese/diagnóstico deste código.']);
                }

                continue;
            }
            $diagnoses[] = ['description' => $description, 'code' => trim((string) ($row['code'] ?? '')) ?: null, 'status' => $row['status'] ?? 'suspected'];
        }

        $actor = $this->actor($request);
        $this->records->saveDraft($encounter, $data, $diagnoses, $actor);
        if ($request->boolean('finalize')) {
            $this->records->finalize($encounter, $actor);

            return redirect()->route('encounters.show', $encounter)->with('status', 'Atendimento finalizado e assinado. O registro está bloqueado; correções entram como adendo.');
        }

        return redirect()->route('encounters.show', $encounter)->with('status', 'Rascunho salvo.');
    }

    public function addendum(Request $request, Encounter $encounter): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:10000']], ['body.required' => 'Escreva o adendo.']);
        $this->records->addAddendum($encounter, $data['body'], $this->actor($request));

        return redirect()->route('encounters.show', $encounter)->with('status', 'Adendo registrado.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
