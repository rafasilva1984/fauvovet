<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Guardian;
use App\Models\Patient;
use App\Models\Unit;
use App\Services\ScheduleService;
use App\Support\BrazilianDocument;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Agenda da clínica ativa: visão dia/semana por unidade, fila de espera, agendar, reagendar e status. */
final class AgendaController extends Controller
{
    public function __construct(private readonly ScheduleService $schedule, private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->schedule->ensureDefaultTypes();
        $units = $this->schedule->allowedUnits();
        $unit = $units->firstWhere('id', (string) $request->query('unidade')) ?? $units->first();
        if (! $unit) {
            return view('agenda.index', ['unit' => null, 'units' => $units]);
        }
        $tz = $unit->timezone;
        $week = $request->query('visao') === 'semana';
        $day = $this->parseDate((string) $request->query('data', ''), $tz);
        $from = $week ? $day->copy()->startOfWeek(Carbon::MONDAY) : $day->copy();
        $to = $from->copy()->addDays($week ? 7 : 1);
        $professionals = $this->schedule->professionals();
        $proId = $professionals->firstWhere('id', (string) $request->query('profissional'))?->id;
        $showCancelled = $request->boolean('canceladas');

        $appointments = Appointment::query()
            ->where('unit_id', $unit->id)
            ->where('starts_at', '>=', $from->copy()->utc())->where('starts_at', '<', $to->copy()->utc())
            ->when($proId, fn ($q) => $q->where('professional_membership_id', $proId))
            ->when(! $showCancelled, fn ($q) => $q->where('status', '!=', 'cancelled'))
            // Semana usa item compacto: não precisa de responsáveis nem profissional (menos consultas e HTML).
            ->with($week ? ['patient:id,name', 'type:id,name,color'] : ['patient:id,name,species,species_detail', 'patient.guardians:id,name,phone', 'type:id,name,color', 'professional.user:id,name'])
            ->orderBy('starts_at')->limit(1000)->get();

        $todayStart = now($tz)->startOfDay();
        $waiting = Appointment::query()->where('unit_id', $unit->id)->where('status', 'waiting')
            ->where('starts_at', '>=', $todayStart->copy()->utc())->where('starts_at', '<', $todayStart->copy()->addDay()->utc())
            ->with(['patient:id,name', 'professional.user:id,name'])->orderBy('checked_in_at')->get();

        return view('agenda.index', [
            'unit' => $unit,
            'units' => $units,
            'tz' => $tz,
            'week' => $week,
            'day' => $day,
            'from' => $from,
            'appointments' => $appointments,
            'byDay' => $appointments->groupBy(fn (Appointment $a) => $a->starts_at->timezone($tz)->format('Y-m-d')),
            'waiting' => $waiting,
            'professionals' => $professionals,
            'proId' => $proId,
            'showCancelled' => $showCancelled,
            'step' => $week ? 7 : 1,
        ]);
    }

    public function create(Request $request): View
    {
        $this->schedule->ensureDefaultTypes();
        $units = $this->schedule->allowedUnits();
        $unit = $units->firstWhere('id', (string) $request->query('unidade')) ?? $units->first();
        $patient = $request->query('paciente') ? Patient::query()->where('status', 'active')->find((string) $request->query('paciente')) : null;
        $search = trim((string) $request->query('buscar', ''));

        return view('agenda.create', [
            'appointment' => null,
            'units' => $units,
            'unit' => $unit,
            'patient' => $patient,
            'search' => $search,
            'candidates' => $patient || $search === '' ? collect() : $this->searchPatients($search),
            'types' => AppointmentType::query()->where('active', true)->orderBy('name')->get(),
            'professionals' => $this->schedule->professionals(),
            'defaults' => [
                'date' => $this->parseDate((string) $request->query('data', ''), $unit->timezone ?? 'America/Sao_Paulo')->format('Y-m-d'),
                'time' => preg_match('/^\d{2}:\d{2}$/', (string) $request->query('hora')) ? (string) $request->query('hora') : '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attrs = $this->validated($request);
        $appointment = $this->schedule->create($attrs, (string) $request->user()?->getAuthIdentifier());
        $tz = $this->unitTz($appointment->unit_id);

        return redirect()->route('agenda.index', ['unidade' => $appointment->unit_id, 'data' => $appointment->starts_at->timezone($tz)->format('Y-m-d')])
            ->with('status', 'Agendado para '.$appointment->starts_at->timezone($tz)->format('d/m/Y \à\s H:i').'.');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['patient.guardians', 'type', 'professional.user:id,name', 'unit']);

        return view('agenda.show', ['appointment' => $appointment, 'tz' => $appointment->unit->timezone]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['patient', 'unit']);

        return view('agenda.create', [
            'appointment' => $appointment,
            'units' => $this->schedule->allowedUnits(),
            'unit' => $appointment->unit,
            'patient' => $appointment->patient,
            'search' => '',
            'candidates' => collect(),
            'types' => AppointmentType::query()->where('active', true)->orWhere('id', $appointment->appointment_type_id)->orderBy('name')->get(),
            'professionals' => $this->schedule->professionals(),
            'defaults' => [
                'date' => $appointment->starts_at->timezone($appointment->unit->timezone)->format('Y-m-d'),
                'time' => $appointment->starts_at->timezone($appointment->unit->timezone)->format('H:i'),
            ],
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $attrs = $this->validated($request, $appointment);
        $this->schedule->update($appointment, $attrs, (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('agenda.show', $appointment)->with('status', 'Agendamento atualizado.');
    }

    public function transition(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', Rule::in(array_keys(Appointment::STATUS))],
            'reason' => ['nullable', 'string', 'max:200'],
            'back' => ['nullable', Rule::in(['agenda', 'show'])],
        ]);
        $this->schedule->transition($appointment, $data['to'], (string) $request->user()?->getAuthIdentifier(), $data['reason'] ?? null);
        $message = Appointment::STATUS[$data['to']].': '.$appointment->patient()->value('name').'.';
        if (($data['back'] ?? 'show') === 'agenda') {
            $tz = $this->unitTz($appointment->unit_id);

            return redirect()->route('agenda.index', ['unidade' => $appointment->unit_id, 'data' => $appointment->starts_at->timezone($tz)->format('Y-m-d')])->with('status', $message);
        }

        return redirect()->route('agenda.show', $appointment)->with('status', $message);
    }

    /**
     * Valida e monta os atributos. Unidade precisa estar entre as permitidas ao vínculo; paciente, tipo e
     * profissional precisam ser desta clínica (validação aqui + FK composta no banco).
     *
     * @return array{unit_id: string, patient_id: string, appointment_type_id: string, professional_membership_id: ?string, starts_at: Carbon, ends_at: Carbon, is_walk_in: bool, notes: ?string}
     */
    private function validated(Request $request, ?Appointment $current = null): array
    {
        $tenantId = $this->context->requireId();
        $units = $this->schedule->allowedUnits();
        $professionalIds = $this->schedule->professionals()->pluck('id')->all();
        $data = $request->validate([
            'unit_id' => ['required', 'string', Rule::in($units->pluck('id')->all())],
            'patient_id' => ['required', 'string', 'size:26', Rule::exists('patients', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'appointment_type_id' => ['required', 'string', 'size:26', Rule::exists('appointment_types', 'id')->where('tenant_id', $tenantId)],
            'professional_membership_id' => ['nullable', 'string', Rule::in($professionalIds)],
            'date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'is_walk_in' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'unit_id.in' => 'Unidade não permitida para o seu acesso.',
            'patient_id.required' => 'Escolha o paciente.',
            'patient_id.exists' => 'Paciente não encontrado (ou inativo).',
            'patient_id.size' => 'Paciente não encontrado (ou inativo).',
            'appointment_type_id.exists' => 'Tipo de atendimento não encontrado.',
            'appointment_type_id.size' => 'Tipo de atendimento não encontrado.',
            'professional_membership_id.in' => 'Profissional inválido.',
            'duration_minutes.min' => 'Duração mínima: 5 minutos.',
            'duration_minutes.max' => 'Duração máxima: 8 horas.',
        ]);
        /** @var Unit $unit */
        $unit = $units->firstWhere('id', $data['unit_id']);
        $start = ScheduleService::toUtc($data['date'], $data['time'], $unit->timezone);

        return [
            'unit_id' => $unit->id,
            'patient_id' => $current->patient_id ?? $data['patient_id'],
            'appointment_type_id' => $data['appointment_type_id'],
            'professional_membership_id' => $data['professional_membership_id'] ?? null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes((int) $data['duration_minutes']),
            'is_walk_in' => (bool) ($data['is_walk_in'] ?? false),
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** @return SupportCollection<int, Patient> */
    private function searchPatients(string $q)
    {
        $like = '%'.addcslashes($q, '%_\\').'%';
        $digits = BrazilianDocument::digits($q);
        $guardianIds = GuardianController::search(Guardian::query(), $q)->limit(200)->pluck('id')->all();

        return Patient::query()->where('status', 'active')
            ->where(function ($w) use ($like, $digits, $guardianIds) {
                $w->where('name', 'like', $like);
                if (strlen($digits) >= 4) {
                    $w->orWhere('microchip', 'like', '%'.$digits.'%');
                }
                if ($guardianIds !== []) {
                    $w->orWhereIn('id', DB::table('guardian_patient')->select('patient_id')
                        ->where('tenant_id', $this->context->requireId())->whereIn('guardian_id', $guardianIds));
                }
            })
            ->with('guardians:id,name')->orderBy('name')->limit(10)->get();
    }

    private function parseDate(string $value, string $tz): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();
            } catch (\Throwable) {
                // data inválida → hoje
            }
        }

        return now($tz)->startOfDay();
    }

    private function unitTz(string $unitId): string
    {
        return (string) (Unit::query()->whereKey($unitId)->value('timezone') ?? 'America/Sao_Paulo');
    }
}
