<?php

namespace App\Http\Controllers;

use App\Models\DocumentBackground;
use App\Models\User;
use App\Services\DocumentBackgroundService;
use App\Services\DocumentService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Fundos de documento da clínica: papel timbrado e receituário (documents:templates). ADR-020. */
final class DocumentBackgroundController extends Controller
{
    public function __construct(private readonly DocumentBackgroundService $backgrounds, private readonly Audit $audit) {}

    public function index(): View
    {
        return view('documents.backgrounds', ['current' => $this->backgrounds->current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'kind' => ['required', Rule::in(array_keys(DocumentBackground::KINDS))],
            'file' => ['required', 'file', 'max:'.intdiv(DocumentBackgroundService::MAX_BYTES, 1024)],
        ], ['file.required' => 'Escolha o arquivo.', 'file.max' => 'Arquivo acima de 10 MB.'], ['file' => 'arquivo']);
        $kind = (string) $request->input('kind');
        $bg = $this->backgrounds->upload($kind, $request->file('file'), $this->user($request));

        return redirect()->route('document-backgrounds.index')->with('status', DocumentBackground::KINDS[$kind].' enviado: já é o fundo padrão'
            .($kind === 'prescription' ? ' das receitas.' : ' dos documentos.').' Abra a prévia para conferir as margens.')->with('fresh', $bg->id);
    }

    public function update(Request $request, DocumentBackground $background): RedirectResponse
    {
        $data = $request->validate([
            'margin_top' => ['required', 'integer', 'min:5', 'max:150'],
            'margin_bottom' => ['required', 'integer', 'min:5', 'max:150'],
            'margin_side' => ['required', 'integer', 'min:5', 'max:60'],
        ], [], ['margin_top' => 'margem superior', 'margin_bottom' => 'margem inferior', 'margin_side' => 'margem lateral']);
        $this->backgrounds->updateMargins($background, ['top' => (int) $data['margin_top'], 'bottom' => (int) $data['margin_bottom'], 'side' => (int) $data['margin_side']], $this->user($request));

        return redirect()->route('document-backgrounds.index')->with('status', 'Margens salvas. Os próximos documentos usam as novas margens.');
    }

    public function retire(Request $request, DocumentBackground $background): RedirectResponse
    {
        $this->backgrounds->retire($background, $this->user($request));

        return redirect()->route('document-backgrounds.index')->with('status', DocumentBackground::KINDS[$background->kind].' removido do uso. Documentos já emitidos não mudam.');
    }

    /** Prévia com texto fictício sobre o fundo (nada é gravado). */
    public function preview(Request $request, DocumentBackground $background, DocumentService $documents): Response
    {
        abort_if($background->retired_at !== null, 404);

        return response($documents->previewBackground($background, $this->user($request)), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="previa-'.$background->kind.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function original(Request $request, DocumentBackground $background): Response
    {
        $bytes = $this->backgrounds->originalBytes($background);
        $this->audit->record('document_background.original_downloaded', (string) $this->user($request)->id, 'document_background', $background->id);

        return response($bytes, 200, [
            'Content-Type' => $background->source_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $background->source_name).'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
