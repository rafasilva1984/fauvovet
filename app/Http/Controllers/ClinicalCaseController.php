<?php

namespace App\Http\Controllers;

use App\Models\ClinicalCase;
use App\Models\ClinicalCasePatient;
use App\Models\Encounter;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\User;
use App\Services\ClinicalCaseService;
use App\Services\DocumentService;
use App\Services\PatientSearch;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Casos científicos da clínica (permissão research:manage: veterinário e titular). */
final class ClinicalCaseController extends Controller
{
    public function __construct(private readonly ClinicalCaseService $cases, private readonly PatientSearch $search, private readonly Audit $audit) {}

    public function index(): View
    {
        $cases = ClinicalCase::query()->withCount('entries')->with('author:id,name')->orderByDesc('updated_at')->paginate(25);

        return view('cases.index', ['cases' => $cases]);
    }

    /** Novo caso: série (filtros da busca na query string) ou relato (?atendimento=ID, finalizado). */
    public function create(Request $request): View
    {
        if ($request->filled('atendimento')) {
            $encounter = $this->finalizedEncounter((string) $request->query('atendimento'));

            return view('cases.create', ['kind' => 'report', 'encounter' => $encounter->load('patient', 'diagnoses'), 'f' => null, 'total' => 1]);
        }
        $f = $this->search->filters($request);
        abort_unless(PatientSearch::hasAdvancedFilter($f) || $f['q'] !== '' || $f['especie'] !== '', 404);

        return view('cases.create', ['kind' => 'series', 'encounter' => null, 'f' => $f, 'total' => $this->search->query($f)->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'question' => ['required', 'string', 'max:2000'],
            'specialty' => ['nullable', 'string', 'max:80'],
        ], ['title.required' => 'Dê um título ao caso.', 'question.required' => 'Escreva a pergunta clínica ou o objetivo.']);
        $user = $this->user($request);
        if ($request->filled('atendimento')) {
            $case = $this->cases->createReport($this->finalizedEncounter((string) $request->query('atendimento')), $data, $user);
        } else {
            $f = $this->search->filters($request);
            abort_unless(PatientSearch::hasAdvancedFilter($f) || $f['q'] !== '' || $f['especie'] !== '', 404);
            if (! $this->search->query($f)->exists()) {
                return back()->withInput()->withErrors(['record' => 'Nenhum paciente atende a estes filtros.']);
            }
            $case = $this->cases->createSeries($f, $data, $user);
        }

        return redirect()->route('cases.show', $case)->with('status', ClinicalCase::KINDS[$case->kind].($case->kind === 'series' ? ' criada' : ' criado').' com '.$case->entries()->count().' '.($case->entries()->count() === 1 ? 'caso' : 'casos').'.');
    }

    public function show(Request $request, ClinicalCase $case): View
    {
        $case->load(['entries.patient:id,name,species,species_detail,breed,sex,birth_date,birth_date_estimated', 'entries.encounter:id,started_at', 'author:id,name']);
        $this->audit->record('clinical_case.viewed', $this->user($request)->id, 'clinical_case', $case->id);

        return view('cases.show', ['case' => $case, 'stats' => $this->cases->stats($case)]);
    }

    public function update(Request $request, ClinicalCase $case): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'question' => ['required', 'string', 'max:2000'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'specialty' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(array_keys(ClinicalCase::STATUS))],
        ]);
        $case->fill($data + ['updated_by' => $this->user($request)->id])->save();

        return back()->with('status', 'Caso atualizado.');
    }

    public function updateEntry(Request $request, ClinicalCase $case, ClinicalCasePatient $entry): RedirectResponse
    {
        abort_unless($entry->case_id === $case->id, 404);
        if (! $case->isOpen()) {
            return back()->withErrors(['record' => 'Caso encerrado: reabra para alterar.']);
        }
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(ClinicalCasePatient::OUTCOMES))],
            'notes' => ['nullable', 'string', 'max:4000'],
            'consent' => ['required', Rule::in(array_keys(ClinicalCasePatient::CONSENT))],
            'consent_on' => ['nullable', 'date', 'before_or_equal:today'],
        ], ['consent_on.before_or_equal' => 'A data do consentimento não pode ser futura.']);
        $this->cases->updateEntry($entry, $data, $this->user($request));

        return redirect()->to(route('cases.show', $case).'#'.$entry->id)->with('status', $entry->label.' atualizado.');
    }

    public function removeEntry(ClinicalCase $case, ClinicalCasePatient $entry): RedirectResponse
    {
        abort_unless($entry->case_id === $case->id, 404);
        if (! $case->isOpen()) {
            return back()->withErrors(['record' => 'Caso encerrado: reabra para alterar.']);
        }
        $label = $entry->label;
        $entry->delete(); // o caso só referencia o prontuário; remover daqui não apaga nada clínico

        return back()->with('status', $label.' removido do caso (o prontuário não é alterado).');
    }

    /** Exportação anonimizada (CSV para planilha/estatística; PDF para leitura). */
    public function export(Request $request, ClinicalCase $case, string $format, DocumentService $documents): Response
    {
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);
        ['rows' => $rows, 'excluded' => $excluded] = $this->cases->anonymizedRows($case);
        $this->audit->record('clinical_case.exported', $this->user($request)->id, 'clinical_case', $case->id, ['format' => $format, 'rows' => count($rows), 'excluded_without_consent' => $excluded]);
        $file = 'casos-'.str($case->title)->slug()->limit(40, '').'.'.$format;
        $names = $this->cases->knownNames($case->entries()->with('patient.guardians:id,name')->get());
        $question = ClinicalCaseService::redact($case->question, $names);
        $summary = ClinicalCaseService::redact((string) $case->summary, $names);

        if ($format === 'csv') {
            $out = fopen('php://temp', 'r+');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: acentos corretos no Excel
            fputcsv($out, ['Caso', 'Espécie', 'Raça', 'Sexo', 'Castração', 'Idade na referência', 'Mês/ano de referência', 'Peso (kg)', 'Diagnósticos do atendimento de referência', 'Desfecho', 'Observações'], ';', '"', '');
            foreach ($rows as $r) {
                // Célula começando com = + - @ vira texto (evita fórmula ao abrir em planilha).
                fputcsv($out, array_map(fn (string $v) => preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, array_values($r)), ';', '"', '');
            }
            rewind($out);
            $body = (string) stream_get_contents($out);

            return response($body, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$file.'"', 'Cache-Control' => 'private, no-store']);
        }
        $html = view('cases.export-pdf', [
            'case' => $case, 'rows' => $rows, 'excluded' => $excluded, 'question' => $question, 'summary' => $summary,
            'tenant' => Tenant::query()->firstOrFail(), 'now' => now('America/Sao_Paulo'),
            'primary' => TenantTheme::query()->value('primary_color') ?? TenantTheme::DEFAULT['primary'],
        ])->render();

        return response($documents->renderHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$file.'"', 'Cache-Control' => 'private, no-store']);
    }

    private function finalizedEncounter(string $id): Encounter
    {
        $encounter = Encounter::query()->findOrFail($id);
        abort_unless($encounter->status === 'finalized', 404);

        return $encounter;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
