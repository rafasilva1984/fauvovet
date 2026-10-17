<?php

namespace App\Http\Controllers;

use App\Documents\Placeholders;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Modelos de documento da clínica. Só variáveis da whitelist; editar gera nova versão (documentos emitidos não mudam). */
final class DocumentTemplateController extends Controller
{
    public function __construct(private readonly DocumentService $documents, private readonly Audit $audit, private readonly TenantContext $context) {}

    public function index(): View
    {
        $this->documents->ensureDefaultTemplates();

        return view('documents.templates', ['templates' => DocumentTemplate::query()->orderByDesc('active')->orderBy('category')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('documents.template-form', ['template' => new DocumentTemplate(['category' => 'guidance', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = DocumentTemplate::create([...$this->validated($request), 'version' => 1, 'active' => true, 'updated_by' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('document_template.created', (string) $request->user()?->getAuthIdentifier(), 'document_template', $template->id);

        return redirect()->route('document-templates.index')->with('status', "Modelo “{$template->name}” criado.");
    }

    public function edit(DocumentTemplate $template): View
    {
        return view('documents.template-form', ['template' => $template]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $this->validated($request, $template);
        $template->fill([...$data, 'active' => $request->boolean('active'), 'updated_by' => $request->user()?->getAuthIdentifier()]);
        if ($template->isDirty(['title', 'body', 'category'])) {
            $template->version++; // nova versão; documentos já emitidos guardam o próprio texto
        }
        $template->save();
        $this->audit->record('document_template.updated', (string) $request->user()?->getAuthIdentifier(), 'document_template', $template->id, ['version' => $template->version]);

        return redirect()->route('document-templates.index')->with('status', "Modelo “{$template->name}” salvo (versão {$template->version}).");
    }

    /** @return array{category: string, name: string, title: string, body: string} */
    private function validated(Request $request, ?DocumentTemplate $current = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(DocumentTemplate::CATEGORIES))],
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('document_templates', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'body' => ['required', 'string', 'min:5', 'max:20000', function (string $attr, mixed $value, Closure $fail) {
                if (($bad = Placeholders::unknown((string) $value)) !== []) {
                    $fail('Variáveis não reconhecidas: {{'.implode('}}, {{', $bad).'}}. Use só as da lista.');
                }
            }],
        ], ['name.unique' => 'Já existe um modelo com este nome.']);
    }
}
