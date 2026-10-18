<?php

namespace App\Http\Controllers;

use App\Models\ExamType;
use App\Services\ExamService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catálogo de exames da clínica (exams:configure). Desativar em vez de apagar. */
final class ExamTypeController extends Controller
{
    public function __construct(private readonly Audit $audit, private readonly TenantContext $context) {}

    public function index(ExamService $exams): View
    {
        $exams->ensureDefaults();

        return view('exams.catalog', ['types' => ExamType::query()->orderByDesc('active')->orderBy('category')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $t = ExamType::create([...$this->validated($request), 'active' => true]);
        $this->audit->record('exam_type.created', (string) $request->user()?->getAuthIdentifier(), 'exam_type', $t->id);

        return redirect()->route('exam-types.index')->with('status', "Exame “{$t->name}” cadastrado.");
    }

    public function update(Request $request, ExamType $type): RedirectResponse
    {
        $type->fill([...$this->validated($request, $type), 'active' => $request->boolean('active')])->save();
        $this->audit->record('exam_type.updated', (string) $request->user()?->getAuthIdentifier(), 'exam_type', $type->id);

        return redirect()->route('exam-types.index')->with('status', "Exame “{$type->name}” salvo.");
    }

    /** @return array{name: string, category: string, sample: ?string} */
    private function validated(Request $request, ?ExamType $current = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('exam_types', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'category' => ['required', Rule::in(array_keys(ExamType::CATEGORIES))],
            'sample' => ['nullable', 'string', 'max:80'],
        ], ['name.unique' => 'Já existe um exame com este nome.'], ['name' => 'nome']);
    }
}
