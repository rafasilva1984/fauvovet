<?php

namespace App\Http\Controllers;

use App\Documents\Placeholders;
use App\Models\DocumentBackground;
use App\Models\DocumentTemplate;
use App\Models\Encounter;
use App\Models\IssuedDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\DocumentBackgroundService;
use App\Services\DocumentService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Emissão, consulta, download e anulação de documentos (receitas, atestados...). Escopo da clínica + auditoria. */
final class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents, private readonly Audit $audit, private readonly DocumentBackgroundService $backgrounds) {}

    /** Escolher o modelo e revisar o texto já preenchido com os dados do paciente/atendimento. */
    public function create(Request $request, Patient $patient): View
    {
        $this->documents->ensureDefaultTemplates();
        $encounter = $request->query('atendimento') ? Encounter::query()->where('patient_id', $patient->id)->find((string) $request->query('atendimento')) : null;
        $templates = DocumentTemplate::query()->where('active', true)->orderBy('category')->orderBy('name')->get()
            ->filter(fn (DocumentTemplate $t) => $this->documents->canIssue($t->category))->values();
        $template = $templates->firstWhere('id', (string) $request->query('modelo'));
        $user = $this->user($request);

        return view('documents.create', [
            'patient' => $patient,
            'encounter' => $encounter,
            'templates' => $templates,
            'template' => $template,
            'prefilled' => $template ? Placeholders::fill($template->body, $this->documents->values($patient, $encounter, $user)) : null,
            'needsCrmv' => trim((string) $user->crmv) === '',
            'backgrounds' => $this->backgrounds->current(),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'string', 'size:26'],
            'encounter_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'body' => ['required', 'string', 'min:5', 'max:20000'],
            'background' => ['nullable', Rule::in(array_keys(DocumentBackground::CHOICES))],
        ], ['body.required' => 'O documento está vazio.']);
        // Lembretes do modelo ainda não preenchidos ("[Dose]", "[via]"...): avisa antes de emitir.
        if (preg_match_all('/\[[^\]\n]{2,60}\]/u', $data['body'], $m) && ! $request->boolean('keep_brackets')) {
            throw ValidationException::withMessages([
                'body' => 'Ainda há trechos entre colchetes para completar: '.implode(', ', array_slice(array_unique($m[0]), 0, 4)).'. Complete-os ou marque “emitir mesmo assim”.',
                'keep_brackets' => 'pending',
            ]);
        }
        $template = DocumentTemplate::query()->where('active', true)->findOrFail($data['template_id']);
        $encounter = ! empty($data['encounter_id']) ? Encounter::query()->where('patient_id', $patient->id)->findOrFail($data['encounter_id']) : null;

        $document = $this->documents->issue($patient, $encounter, $template, $template->category, $data['title'], $data['body'], $this->user($request),
            $data['background'] ?? $template->background);

        return redirect()->route('documents.show', $document)->with('status', 'Documento emitido. O PDF foi gerado e guardado.');
    }

    public function show(Request $request, IssuedDocument $document): View
    {
        $document->load(['patient', 'issuer:id,name,crmv', 'encounter:id,started_at', 'prescriptionItems']);
        $this->audit->record('document.viewed', (string) $this->user($request)->id, 'document', $document->id);

        return view('documents.show', ['document' => $document]);
    }

    /** PDF guardado (com conferência de integridade). Download auditado; nunca servido como arquivo público. */
    public function pdf(Request $request, IssuedDocument $document): Response
    {
        $bytes = $this->documents->pdfContents($document);
        $this->audit->record('document.downloaded', (string) $this->user($request)->id, 'document', $document->id);
        $name = str($document->title.' '.$document->patient()->value('name').' '.$document->issued_at->format('Y-m-d'))->slug().'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('baixar') ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function void(Request $request, IssuedDocument $document): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], ['reason.required' => 'Informe o motivo da anulação.']);
        $this->documents->void($document, $data['reason'], $this->user($request));

        return redirect()->route('documents.show', $document)->with('status', 'Documento anulado. O PDF original continua guardado.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
