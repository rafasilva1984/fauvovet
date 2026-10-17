<?php

namespace App\Http\Controllers;

use App\Files\FileTypeDetector;
use App\Models\Attachment;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Services\AttachmentService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Anexos: envio (equipe clínica), abertura de imagem / download, remoção com motivo.
 * Imagens abrem no navegador; PDF e DOCX de terceiros são SEMPRE baixados (nunca exibidos dentro do sistema).
 */
final class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments, private readonly Audit $audit) {}

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $maxKb = (int) config('fauvovet.attachments.max_kb');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'category' => ['required', Rule::in(array_keys(Attachment::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'encounter_id' => ['nullable', 'string', 'size:26'],
        ], [
            'file.required' => 'Escolha um arquivo.',
            'file.uploaded' => 'O arquivo não chegou ao servidor (pode ser maior que o limite de '.intdiv($maxKb, 1024).' MB).',
            'file.max' => 'Arquivo maior que '.intdiv($maxKb, 1024).' MB.',
        ]);
        $encounter = ! empty($data['encounter_id']) ? Encounter::query()->where('patient_id', $patient->id)->findOrFail($data['encounter_id']) : null;
        $this->attachments->store($request->file('file'), $patient, $encounter, $data, $this->user($request));

        return back()->with('status', 'Anexo enviado.');
    }

    /** Imagem: abre no navegador. PDF/DOCX: download. Sempre com nosniff, sem cache e sandbox. */
    public function download(Request $request, Attachment $attachment): Response
    {
        $bytes = $this->attachments->contents($attachment);
        $this->audit->record('attachment.downloaded', $this->user($request)->id, 'attachment', $attachment->id);
        $inline = $attachment->isImage() && ! $request->boolean('baixar');
        $name = str(pathinfo(FileTypeDetector::safeName($attachment->original_name), PATHINFO_FILENAME))->slug()->limit(80, '').'.'.$attachment->extension;

        return response($bytes, 200, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Mesmo que um navegador tente interpretar o arquivo, nada roda: sem scripts, sem origem própria.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
        ]);
    }

    public function remove(Request $request, Attachment $attachment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']], ['reason.required' => 'Informe o motivo da remoção.']);
        $this->attachments->remove($attachment, $data['reason'], $this->user($request));

        return back()->with('status', 'Anexo removido da ficha. O arquivo original continua guardado para histórico.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
