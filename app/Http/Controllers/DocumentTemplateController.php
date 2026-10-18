<?php

namespace App\Http\Controllers;

use App\Documents\Placeholders;
use App\Documents\TemplateImporter;
use App\Models\DocumentBackground;
use App\Models\DocumentTemplate;
use App\Services\DocumentBackgroundService;
use App\Services\DocumentService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Modelos de documento da clínica. Só variáveis da whitelist; editar gera nova versão (documentos emitidos não mudam). */
final class DocumentTemplateController extends Controller
{
    public function __construct(private readonly DocumentService $documents, private readonly Audit $audit, private readonly TenantContext $context, private readonly DocumentBackgroundService $backgrounds) {}

    public function index(): View
    {
        $this->documents->ensureDefaultTemplates();

        return view('documents.templates', ['templates' => DocumentTemplate::query()->orderByDesc('active')->orderBy('category')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('documents.template-form', ['template' => new DocumentTemplate(['category' => 'guidance', 'active' => true, 'background' => 'auto']), 'backgrounds' => $this->backgrounds->current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = DocumentTemplate::create([...$this->validated($request), 'version' => 1, 'active' => true, 'updated_by' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('document_template.created', (string) $request->user()?->getAuthIdentifier(), 'document_template', $template->id);

        return redirect()->route('document-templates.index')->with('status', "Modelo “{$template->name}” criado.");
    }

    public function importForm(): View
    {
        return view('documents.template-import');
    }

    /** Importa DOCX/PDF como modelo desativado, para revisão (original preservado com hash). */
    public function import(Request $request, TemplateImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.intdiv(TemplateImporter::MAX_BYTES, 1024)],
            'category' => ['required', Rule::in(array_keys(DocumentTemplate::CATEGORIES))],
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('document_templates', 'name')->where('tenant_id', $this->context->requireId())],
        ], ['name.unique' => 'Já existe um modelo com este nome.', 'file.max' => 'Arquivo acima de 10 MB.'], ['file' => 'arquivo', 'name' => 'nome do modelo']);
        $userId = (string) $request->user()?->getAuthIdentifier();
        $r = $importer->import($request->file('file'), (string) $request->input('category'), (string) $request->input('name'), $userId);
        $this->audit->record('document_template.imported', $userId, 'document_template', $r['template']->id, ['mime' => $r['template']->source_mime, 'suggestions' => count($r['suggestions'])]);

        return redirect()->route('document-templates.edit', $r['template'])->with('status', 'Modelo importado e DESATIVADO para revisão. Confira o texto, as variáveis sugeridas e marque “Ativo” ao salvar.')
            ->with('import_suggestions', $r['suggestions']);
    }

    /** Baixa o arquivo original importado (conferência de integridade). */
    public function original(Request $request, DocumentTemplate $template): Response
    {
        abort_if($template->source_path === null, 404);
        $bytes = Storage::disk('local')->get($template->source_path);
        abort_if($bytes === null || ! hash_equals((string) $template->source_sha256, hash('sha256', $bytes)), 500, 'Original ausente ou alterado.');
        $this->audit->record('document_template.original_downloaded', (string) $request->user()?->getAuthIdentifier(), 'document_template', $template->id);

        return response($bytes, 200, [
            'Content-Type' => (string) $template->source_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', (string) $template->source_name).'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    public function edit(DocumentTemplate $template): View
    {
        return view('documents.template-form', ['template' => $template, 'backgrounds' => $this->backgrounds->current()]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $this->validated($request, $template);
        $template->fill([...$data, 'active' => $request->boolean('active'), 'updated_by' => $request->user()?->getAuthIdentifier()]);
        if ($template->isDirty(['title', 'body', 'category', 'background'])) {
            $template->version++; // nova versão; documentos já emitidos guardam o próprio texto
        }
        $template->save();
        $this->audit->record('document_template.updated', (string) $request->user()?->getAuthIdentifier(), 'document_template', $template->id, ['version' => $template->version]);

        return redirect()->route('document-templates.index')->with('status', "Modelo “{$template->name}” salvo (versão {$template->version}).");
    }

    /** @return array{category: string, name: string, title: string, body: string, background: string} */
    private function validated(Request $request, ?DocumentTemplate $current = null): array
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(DocumentTemplate::CATEGORIES))],
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('document_templates', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'background' => ['nullable', Rule::in(array_keys(DocumentBackground::CHOICES))],
            'body' => ['required', 'string', 'min:5', 'max:20000', function (string $attr, mixed $value, Closure $fail) {
                if (($bad = Placeholders::unknown((string) $value)) !== []) {
                    $fail('Variáveis não reconhecidas: {{'.implode('}}, {{', $bad).'}}. Use só as da lista.');
                }
            }],
        ], ['name.unique' => 'Já existe um modelo com este nome.']);
        $data['background'] ??= $current->background ?? 'auto';

        return $data;
    }
}
