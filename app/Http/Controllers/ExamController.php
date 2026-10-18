<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\ExamRequest;
use App\Models\ExamType;
use App\Models\Patient;
use App\Models\User;
use App\Services\ExamService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Exames: painel por situação, pedido na ficha, etapas, resultado (texto/laudo), revisão e cancelamento. */
final class ExamController extends Controller
{
    public function __construct(private readonly ExamService $exams, private readonly TenantContext $context, private readonly Audit $audit) {}

    public function index(Request $request): View
    {
        $status = in_array($s = (string) $request->query('situacao', 'abertos'), ['abertos', ...array_keys(ExamRequest::STATUS)], true) ? $s : 'abertos';
        $list = ExamRequest::query()->with(['patient:id,name,species', 'type:id,name,category'])
            ->when($status === 'abertos', fn ($q) => $q->whereNotIn('status', ['reviewed', 'cancelled']), fn ($q) => $q->where('status', $status))
            ->orderBy('requested_at')->limit(200)->get();

        return view('exams.index', ['list' => $list, 'status' => $status, 'counts' => $this->exams->counts()]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        abort_unless($patient->status === 'active', 422, 'Paciente inativo.');
        $tenant = $this->context->requireId();
        $data = $request->validate([
            'exam_type_id' => ['required', 'string', 'size:26', Rule::exists('exam_types', 'id')->where('tenant_id', $tenant)],
            'encounter_id' => ['nullable', 'string', 'size:26', Rule::exists('encounters', 'id')->where('tenant_id', $tenant)],
            'lab_name' => ['nullable', 'string', 'max:120'],
            'request_notes' => ['nullable', 'string', 'max:500'],
        ], ['exam_type_id.required' => 'Escolha o exame.'], ['exam_type_id' => 'exame']);
        $exam = $this->exams->request($patient, ExamType::query()->findOrFail($data['exam_type_id']),
            isset($data['encounter_id']) ? Encounter::query()->findOrFail($data['encounter_id']) : null, $data, $this->actor($request));

        return redirect()->route('exams.show', $exam)->with('status', 'Exame solicitado.');
    }

    public function show(Request $request, ExamRequest $exam): View
    {
        $exam->load(['patient', 'type', 'requester:id,name', 'reviewer:id,name,crmv', 'resultAttachment']);
        $this->audit->record('exam.viewed', (string) $request->user()?->getAuthIdentifier(), 'exam', $exam->id);

        return view('exams.show', ['exam' => $exam]);
    }

    public function advance(Request $request, ExamRequest $exam): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(array_keys(ExamRequest::STATUS))]]);
        $this->exams->advance($exam, (string) $request->input('status'), $this->actor($request));

        return redirect()->route('exams.show', $exam)->with('status', 'Situação: '.ExamRequest::STATUS[$exam->status].'.');
    }

    public function result(Request $request, ExamRequest $exam): RedirectResponse
    {
        $request->validate([
            'result_text' => ['nullable', 'string', 'max:60000'],
            'file' => ['nullable', 'file', 'max:'.(int) config('fauvovet.attachments.max_kb')],
        ], [], ['result_text' => 'resultado', 'file' => 'laudo']);
        $this->exams->registerResult($exam, $request->input('result_text'), $request->file('file'), $this->actor($request));

        return redirect()->route('exams.show', $exam)->with('status', 'Resultado registrado.');
    }

    public function review(Request $request, ExamRequest $exam): RedirectResponse
    {
        $request->validate(['interpretation' => ['required', 'string', 'min:3', 'max:5000'], 'confirm' => ['accepted']],
            ['confirm.accepted' => 'Confirme a revisão: depois dela o exame não muda mais.'], ['interpretation' => 'interpretação']);
        $this->exams->review($exam, (string) $request->input('interpretation'), $this->actor($request));

        return redirect()->route('exams.show', $exam)->with('status', 'Exame revisado e assinado.');
    }

    public function cancel(Request $request, ExamRequest $exam): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], [], ['reason' => 'motivo']);
        $this->exams->cancel($exam, (string) $request->input('reason'), $this->actor($request));

        return redirect()->route('exams.show', $exam)->with('status', 'Exame cancelado.');
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
