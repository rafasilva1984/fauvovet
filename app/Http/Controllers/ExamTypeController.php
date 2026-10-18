<?php

namespace App\Http\Controllers;

use App\Models\ExamAnalyte;
use App\Models\ExamReferenceRange;
use App\Models\ExamType;
use App\Models\User;
use App\Services\ExamService;
use App\Support\Audit;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Catálogo de exames da clínica (exams:configure). Desativar em vez de apagar. */
final class ExamTypeController extends Controller
{
    public function __construct(private readonly Audit $audit, private readonly TenantContext $context) {}

    public function index(ExamService $exams): View
    {
        $exams->ensureDefaults();

        return view('exams.catalog', ['types' => ExamType::query()->withCount('analytes')->orderByDesc('active')->orderBy('category')->orderBy('name')->get()]);
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

    /** Parâmetros (analitos) do exame e faixas de referência por espécie. */
    public function show(ExamService $exams, ExamType $type): View
    {
        $exams->ensureDefaultAnalytes();

        return view('exams.analytes', ['type' => $type,
            'analytes' => ExamAnalyte::query()->with('ranges')->where('exam_type_id', $type->id)->orderByDesc('active')->orderBy('position')->orderBy('name')->get()]);
    }

    public function storeAnalyte(Request $request, ExamService $exams, ExamType $type): RedirectResponse
    {
        $a = $exams->saveAnalyte($type, $this->analyteData($request, $type), null, $this->actor($request));

        return redirect()->route('exam-types.show', $type)->with('status', "Parâmetro “{$a->name}” cadastrado.");
    }

    public function updateAnalyte(Request $request, ExamService $exams, ExamAnalyte $analyte): RedirectResponse
    {
        $type = ExamType::query()->findOrFail($analyte->exam_type_id);
        $exams->saveAnalyte($type, [...$this->analyteData($request, $type, $analyte), 'active' => $request->boolean('active')], $analyte, $this->actor($request));

        return redirect()->route('exam-types.show', $type)->with('status', "Parâmetro “{$analyte->name}” salvo.");
    }

    public function storeRange(Request $request, ExamService $exams, ExamAnalyte $analyte): RedirectResponse
    {
        $request->validate(['species' => ['required', Rule::in(['*', ...array_keys(Species::LABELS)])], 'ref_min' => ['nullable', 'string', 'max:20'], 'ref_max' => ['nullable', 'string', 'max:20']],
            [], ['species' => 'espécie', 'ref_min' => 'mínimo', 'ref_max' => 'máximo']);
        $min = ExamService::parseNumber($request->input('ref_min'));
        $max = ExamService::parseNumber($request->input('ref_max'));
        foreach (['ref_min' => $min, 'ref_max' => $max] as $k => $v) {
            if ($v === null && trim((string) $request->input($k)) !== '') {
                throw ValidationException::withMessages([$k => 'Use só números (ex.: 0,5).']);
            }
        }
        $exams->saveRange($analyte, (string) $request->input('species'), $min, $max, $this->actor($request));

        return redirect()->route('exam-types.show', $analyte->exam_type_id)->with('status', "Faixa de “{$analyte->name}” salva.");
    }

    public function destroyRange(Request $request, ExamReferenceRange $range): RedirectResponse
    {
        $analyte = ExamAnalyte::query()->findOrFail($range->analyte_id);
        $range->delete(); // configuração (não é registro clínico): os resultados guardam a própria faixa
        $this->audit->record('exam_range.removed', (string) $this->actor($request)->id, 'exam_analyte', $analyte->id, ['species' => $range->species]);

        return redirect()->route('exam-types.show', $analyte->exam_type_id)->with('status', 'Faixa removida.');
    }

    /** @return array{name: string, unit: ?string} */
    private function analyteData(Request $request, ExamType $type, ?ExamAnalyte $current = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:80', Rule::unique('exam_analytes', 'name')->where('tenant_id', $this->context->requireId())->where('exam_type_id', $type->id)->ignore($current?->id)],
            'unit' => ['nullable', 'string', 'max:30'],
        ], ['name.unique' => 'Este exame já tem um parâmetro com este nome.'], ['name' => 'nome', 'unit' => 'unidade']);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
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
