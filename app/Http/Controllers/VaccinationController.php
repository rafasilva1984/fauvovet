<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Services\DocumentService;
use App\Services\VaccinationService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Carteira de vacinação: registrar aplicação, anular, vencimentos da clínica e carteira em PDF. */
final class VaccinationController extends Controller
{
    public function __construct(private readonly VaccinationService $vaccinations, private readonly Audit $audit) {}

    /** Vencimentos: atrasadas e próximas (até 30 dias) — para a recepção avisar os tutores. */
    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('dias'), [7, 30, 60, 90], true) ? (int) $request->query('dias') : 30;

        return view('vaccinations.index', ['due' => $this->vaccinations->dueList($days, 200), 'days' => $days]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->status === 'active', 422, 'Paciente inativo.');
        $data = $request->validate([
            'vaccine_id' => ['required', 'string', 'size:26'],
            'encounter_id' => ['nullable', 'string', 'size:26'],
            'applied_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'lot' => ['required', 'string', 'min:1', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'lot_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'site' => ['nullable', Rule::in(Vaccination::SITES)],
            'next_due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:applied_on'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'lot.required' => 'Informe o lote (está na etiqueta do frasco).',
            'applied_on.before_or_equal' => 'A data da aplicação não pode ser no futuro.',
            'next_due_on.after_or_equal' => 'A próxima dose deve ser depois da aplicação.',
        ]);
        $vaccine = Vaccine::query()->where('active', true)->findOrFail($data['vaccine_id']);
        $encounter = ! empty($data['encounter_id']) ? Encounter::query()->where('patient_id', $patient->id)->findOrFail($data['encounter_id']) : null;
        $v = $this->vaccinations->register($patient, $vaccine, $encounter, $data, $this->user($request));

        return back()->with('status', "Vacina registrada: {$vaccine->name}, dose {$v->dose_number}".($v->next_due_on ? '. Próxima em '.$v->next_due_on->format('d/m/Y').'.' : '.'));
    }

    public function void(Request $request, Vaccination $vaccination): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], ['reason.required' => 'Informe o motivo da anulação.']);
        $this->vaccinations->void($vaccination, $data['reason'], $this->user($request));

        return back()->with('status', 'Aplicação anulada. Ela continua no histórico, riscada.');
    }

    /** Carteira em PDF (gerada na hora, não guardada; o histórico é o próprio registro). */
    public function card(Request $request, Patient $patient, DocumentService $documents): Response
    {
        $user = $this->user($request);
        $rows = Vaccination::query()->where('patient_id', $patient->id)->whereNull('voided_at')
            ->with(['vaccine:id,name', 'appliedBy.user:id,name,crmv'])->orderBy('applied_on')->get();
        $html = view('vaccinations.card-pdf', [
            'tenant' => Tenant::query()->firstOrFail(), 'patient' => $patient, 'rows' => $rows,
            'values' => $documents->values($patient, null, $user), 'now' => now('America/Sao_Paulo'),
            'primary' => TenantTheme::query()->value('primary_color') ?? TenantTheme::DEFAULT['primary'],
        ])->render();
        $pdf = $documents->renderHtml($html);
        $this->audit->record('vaccination.card_exported', $user->id, 'patient', $patient->id);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="carteira-vacinacao-'.str($patient->name)->slug().'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
