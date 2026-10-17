<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientWeight;
use App\Models\User;
use App\Services\ClinicalRecordService;
use App\Services\PatientRegistry;
use App\Support\BrazilianDocument;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pacientes da clínica ativa, com cadastro rápido do responsável e vínculos muitos-para-muitos. */
final class PatientController extends Controller
{
    public function __construct(private readonly PatientRegistry $registry, private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $species = (string) $request->query('especie', '');
        $patients = Patient::query()
            ->with('guardians:id,name,phone')
            ->when($q !== '', function ($query) use ($q) {
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
            ->when(in_array($species, Species::codes(), true), fn ($query) => $query->where('species', $species))
            ->when(! $request->boolean('todos'), fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('patients.index', ['patients' => $patients, 'q' => $q, 'species' => $species]);
    }

    public function create(Request $request): View
    {
        $guardian = $request->query('responsavel') ? Guardian::query()->find((string) $request->query('responsavel')) : null;

        return view('patients.create', ['patient' => new Patient(['sex' => 'unknown', 'neutered' => 'unknown', 'species' => 'canine']), 'guardian' => $guardian]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->context->requireId();
        $existing = $request->filled('guardian_id');
        $rules = [
            ...$this->registry->patientRules($request->all()),
            'relationship' => ['nullable', Rule::in(array_keys(Patient::RELATIONSHIPS))],
            'guardian_id' => ['nullable', 'string', 'size:26', Rule::exists('guardians', 'id')->where('tenant_id', $tenantId)],
        ];
        if (! $existing) {
            $rules = [...$rules, ...$this->registry->guardianRules($request->all(), 'g_')];
        }
        $data = $request->validate($rules, [
            ...$this->registry->patientMessages(),
            ...$this->registry->guardianMessages('g_'),
            'guardian_id.exists' => 'Responsável não encontrado.',
            'guardian_id.size' => 'Responsável não encontrado.',
        ]);
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $attrs = $this->registry->patientAttributes($data);

        if ($existing) {
            $guardian = Guardian::query()->findOrFail($data['guardian_id']);
            $same = $guardian->patients()->where('patients.name', $attrs['name'])->where('patients.species', $attrs['species'])->first(['patients.id', 'patients.name']);
            if ($same && ! $request->boolean('confirm_duplicate')) {
                return back()->withInput()->with('duplicates', [['id' => $same->id, 'name' => $same->name, 'type' => 'patient']]);
            }
        } else {
            $gAttrs = $this->registry->guardianAttributes($data, 'g_');
            if (! $request->boolean('confirm_duplicate') && ($dups = $this->registry->guardianDuplicates($gAttrs))->isNotEmpty()) {
                return back()->withInput()->with('duplicates', $dups->map(fn (Guardian $g) => ['id' => $g->id, 'name' => $g->name, 'type' => 'guardian'])->all());
            }
        }

        $patient = DB::transaction(function () use ($existing, $data, $attrs, $actorId) {
            $guardian = $existing
                ? Guardian::query()->findOrFail($data['guardian_id'])
                : $this->registry->createGuardian($this->registry->guardianAttributes($data, 'g_'), $actorId);

            return $this->registry->createPatient($attrs, $guardian, $data['relationship'] ?? 'primary', $actorId);
        });

        return redirect()->route('patients.show', $patient)->with('status', "Paciente “{$patient->name}” cadastrado.");
    }

    public function show(Request $request, Patient $patient): View
    {
        $patient->load(['guardians' => fn ($q) => $q->orderBy('name')]);
        $term = trim((string) $request->query('vincular', ''));
        $candidates = $term === '' ? collect() : GuardianController::search(Guardian::query(), $term)
            ->where('status', 'active')
            ->whereNotIn('id', $patient->guardians->pluck('id'))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'phone', 'email']);

        $appointments = $this->context->can('schedule:read')
            ? Appointment::query()->where('patient_id', $patient->id)->where('starts_at', '>=', now()->subDays(1))
                ->whereNotIn('status', ['cancelled'])->with(['type:id,name', 'unit:id,name,timezone', 'professional.user:id,name'])
                ->orderBy('starts_at')->limit(5)->get()
            : null;

        // Prontuário: só para a equipe clínica, e cada abertura fica registrada (auditoria de acesso).
        $clinical = null;
        if ($this->context->can('clinical:read')) {
            /** @var User $user */
            $user = $request->user();
            app(ClinicalRecordService::class)->recordView('patient.clinical_viewed', 'patient', $patient->id, $user);
            $clinical = [
                'alerts' => PatientAlert::query()->where('patient_id', $patient->id)->whereNull('resolved_at')
                    ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")->orderBy('created_at')->get(),
                'weights' => PatientWeight::query()->where('patient_id', $patient->id)->orderByDesc('measured_at')->limit(12)->get()->reverse()->values(),
                'encounters' => Encounter::query()->where('patient_id', $patient->id)
                    ->with(['diagnoses', 'author:id,name', 'signer:id,name', 'unit:id,name,timezone', 'type:id,name'])
                    ->withCount('addenda')->orderByDesc('started_at')->limit(50)->get(),
            ];
        }

        return view('patients.show', ['patient' => $patient, 'term' => $term, 'candidates' => $candidates, 'appointments' => $appointments, 'clinical' => $clinical]);
    }

    public function edit(Patient $patient): View
    {
        return view('patients.edit', ['patient' => $patient]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate($this->registry->patientRules($request->all()), $this->registry->patientMessages());
        $this->registry->updatePatient($patient, $this->registry->patientAttributes($data), (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('patients.show', $patient)->with('status', 'Paciente atualizado.');
    }

    public function link(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'string', 'size:26', Rule::exists('guardians', 'id')->where('tenant_id', $this->context->requireId())],
            'relationship' => ['required', Rule::in(array_keys(Patient::RELATIONSHIPS))],
        ], ['guardian_id.exists' => 'Responsável não encontrado.', 'guardian_id.size' => 'Responsável não encontrado.']);
        $guardian = Guardian::query()->findOrFail($data['guardian_id']);
        $this->registry->link($patient, $guardian, $data['relationship'], (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('patients.show', $patient)->with('status', "{$guardian->name} vinculado(a) ao paciente.");
    }

    public function unlink(Request $request, Patient $patient, Guardian $guardian): RedirectResponse
    {
        $this->registry->unlink($patient, $guardian, (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('patients.show', $patient)->with('status', "{$guardian->name} desvinculado(a) do paciente.");
    }
}
