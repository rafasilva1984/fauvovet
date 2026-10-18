<?php

namespace App\Services;

use App\Models\Encounter;
use App\Models\ExamAnalyte;
use App\Models\ExamReferenceRange;
use App\Models\ExamRequest;
use App\Models\ExamResultValue;
use App\Models\ExamType;
use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exames (Fase 3, ADR-019). Regras:
 *  - pedir/andar etapas/registrar resultado = clinical:write; REVISAR (interpretação assinada) = clinical:sign;
 *  - etapas só avançam (pode pular: exame feito na clínica vai direto a "recebido"); cada etapa grava data/hora;
 *  - revisado e cancelado são finais; laudo enviado vira anexo do prontuário (texto pesquisável);
 *  - toda mudança reindexa a busca clínica do paciente e fica na auditoria (sem o conteúdo).
 */
final class ExamService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Audit $audit,
        private readonly ClinicalSearchIndex $search,
        private readonly AttachmentService $attachments,
    ) {}

    public function ensureDefaults(): void
    {
        if (! ExamType::query()->exists()) {
            DB::transaction(function () {
                foreach (ExamType::DEFAULTS as [$name, $category, $sample]) {
                    ExamType::create(['name' => $name, 'category' => $category, 'sample' => $sample, 'active' => true]);
                }
            });
        }
        $this->ensureDefaultAnalytes();
    }

    /** Analitos iniciais (nome/unidade, SEM faixa) nos exames padrão que ainda não têm nenhum. */
    public function ensureDefaultAnalytes(): void
    {
        $types = ExamType::query()->whereIn('name', array_keys(ExamAnalyte::DEFAULTS))->get(['id', 'name']);
        $withAnalytes = ExamAnalyte::query()->whereIn('exam_type_id', $types->pluck('id'))->distinct()->pluck('exam_type_id')->all();
        foreach ($types->whereNotIn('id', $withAnalytes) as $type) {
            DB::transaction(function () use ($type) {
                foreach (ExamAnalyte::DEFAULTS[$type->name] as $i => [$name, $unit]) {
                    ExamAnalyte::create(['exam_type_id' => $type->id, 'name' => $name, 'unit' => $unit, 'position' => $i + 1, 'active' => true]);
                }
            });
        }
    }

    /** "1.234,5" / "1234.5" / "0,8" → número; texto que não é número → null. */
    public static function parseNumber(?string $raw): ?float
    {
        $s = str_replace([' ', "\u{00A0}"], '', trim((string) $raw));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s); // formato brasileiro
        }

        return preg_match('/^[-+]?\d+(\.\d+)?$/', $s) ? (float) $s : null;
    }

    /**
     * Registra/corrige os valores estruturados (enquanto o exame não foi revisado). A faixa usada fica copiada no
     * valor (vem do catálogo para a espécie, mas pode ser ajustada para a do laudo). Campo vazio = sem valor.
     *
     * @param  array<string, array{value?: ?string, ref_min?: ?string, ref_max?: ?string}>  $input  analito => dados
     */
    public function recordValues(ExamRequest $exam, array $input, User $actor): int
    {
        if (! $exam->isOpen()) {
            throw ValidationException::withMessages(['values' => 'Exame revisado ou cancelado não recebe novos valores.']);
        }
        $analytes = ExamAnalyte::query()->where('exam_type_id', $exam->exam_type_id)->whereIn('id', array_keys($input))->get()->keyBy('id');
        $errors = [];
        $rows = [];
        foreach ($input as $analyteId => $row) {
            $analyte = $analytes->get($analyteId);
            if ($analyte === null) {
                continue;
            }
            $raw = trim((string) ($row['value'] ?? ''));
            $min = self::parseNumber($row['ref_min'] ?? null);
            $max = self::parseNumber($row['ref_max'] ?? null);
            foreach (['ref_min' => $min, 'ref_max' => $max] as $k => $v) {
                if ($v === null && trim((string) ($row[$k] ?? '')) !== '') {
                    $errors["values.{$analyteId}.{$k}"] = "Faixa de {$analyte->name}: use só números (ex.: 0,5).";
                }
            }
            if ($min !== null && $max !== null && $min > $max) {
                $errors["values.{$analyteId}.ref_min"] = "Faixa de {$analyte->name}: o mínimo é maior que o máximo.";
            }
            $number = self::parseNumber($raw);
            $rows[$analyteId] = $raw === '' ? null : [
                'value' => $number, 'value_text' => $number === null ? mb_substr($raw, 0, 60) : null, 'unit' => $analyte->unit,
                'ref_min' => $min, 'ref_max' => $max, 'flag' => ExamResultValue::flagFor($number, $min, $max),
            ];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $saved = 0;
        DB::transaction(function () use ($exam, $rows, $actor, &$saved) {
            $measuredAt = $exam->collected_at ?? $exam->received_at ?? now();
            $existing = ExamResultValue::query()->where('exam_request_id', $exam->id)->get()->keyBy('analyte_id');
            foreach ($rows as $analyteId => $data) {
                $current = $existing->get($analyteId);
                if ($data === null) {
                    // Exame ainda aberto: apagar um valor digitado por engano é permitido (sem evento do model).
                    $current && ExamResultValue::query()->whereKey($current->id)->delete();

                    continue;
                }
                $value = $current ?? new ExamResultValue(['exam_request_id' => $exam->id, 'patient_id' => $exam->patient_id, 'analyte_id' => $analyteId]);
                $value->fill([...$data, 'measured_at' => $measuredAt, 'recorded_by' => $actor->id])->save();
                $saved++;
            }
            $exam->result_by = $actor->id;
            if ($saved > 0 && array_search($exam->status, ExamRequest::FLOW, true) < array_search('received', ExamRequest::FLOW, true)) {
                $this->moveTo($exam, 'received');
            }
            $exam->save();
        });
        $this->search->reindexPatient($exam->patient_id);
        $this->audit->record('exam.values_recorded', $actor->id, 'exam', $exam->id, ['count' => $saved]);

        return $saved;
    }

    /**
     * Linhas para a tela do exame: analito, valor atual, faixa sugerida (catálogo/espécie) e histórico do paciente
     * (exames não cancelados, em ordem cronológica) para o gráfico comparativo.
     *
     * @return Collection<int, array{analyte: ExamAnalyte, value: ?ExamResultValue, suggested: ?ExamReferenceRange, history: Collection<int, ExamResultValue>}>
     */
    public function valueRows(ExamRequest $exam): Collection
    {
        $values = ExamResultValue::query()->where('exam_request_id', $exam->id)->get()->keyBy('analyte_id');
        $analytes = ExamAnalyte::query()->with('ranges')->where('exam_type_id', $exam->exam_type_id)
            ->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $values->keys()))->orderBy('position')->orderBy('name')->get();
        $history = ExamResultValue::query()->where('patient_id', $exam->patient_id)->whereIn('analyte_id', $analytes->pluck('id'))
            ->whereIn('exam_request_id', ExamRequest::query()->select('id')->where('patient_id', $exam->patient_id)->where('status', '!=', 'cancelled'))
            ->orderBy('measured_at')->get();
        $species = (string) $exam->patient->species;

        return $analytes->map(fn (ExamAnalyte $a) => [
            'analyte' => $a, 'value' => $values->get($a->id), 'suggested' => $a->rangeFor($species),
            'history' => $history->where('analyte_id', $a->id)->values()->toBase(),
        ])->values();
    }

    /** @param array{name: string, unit: ?string, position?: ?int, active?: bool} $data */
    public function saveAnalyte(ExamType $type, array $data, ?ExamAnalyte $analyte, User $actor): ExamAnalyte
    {
        $analyte ??= new ExamAnalyte(['exam_type_id' => $type->id, 'position' => (int) ExamAnalyte::query()->where('exam_type_id', $type->id)->max('position') + 1]);
        $analyte->fill(array_filter($data, fn ($v) => $v !== null) + ['unit' => null])->save();
        $this->audit->record('exam_analyte.saved', $actor->id, 'exam_analyte', $analyte->id);

        return $analyte;
    }

    public function saveRange(ExamAnalyte $analyte, string $species, ?float $min, ?float $max, User $actor): void
    {
        if ($species !== '*' && ! isset(Species::LABELS[$species])) {
            throw ValidationException::withMessages(['species' => 'Espécie inválida.']);
        }
        if ($min === null && $max === null) {
            throw ValidationException::withMessages(['ref_min' => 'Informe o mínimo, o máximo ou os dois.']);
        }
        if ($min !== null && $max !== null && $min > $max) {
            throw ValidationException::withMessages(['ref_min' => 'O mínimo é maior que o máximo.']);
        }
        ExamReferenceRange::query()->updateOrCreate(['analyte_id' => $analyte->id, 'species' => $species], ['ref_min' => $min, 'ref_max' => $max]);
        $this->audit->record('exam_range.saved', $actor->id, 'exam_analyte', $analyte->id, ['species' => $species]);
    }

    /** @param array{lab_name: ?string, request_notes: ?string} $data */
    public function request(Patient $patient, ExamType $type, ?Encounter $encounter, array $data, User $actor): ExamRequest
    {
        if (! $type->active) {
            throw ValidationException::withMessages(['exam_type_id' => 'Este exame está desativado no catálogo.']);
        }
        if ($encounter && $encounter->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['exam_type_id' => 'O atendimento é de outro paciente.']);
        }
        $exam = ExamRequest::create([
            'patient_id' => $patient->id, 'encounter_id' => $encounter?->id, 'exam_type_id' => $type->id, 'lab_name' => $data['lab_name'] ?? null,
            'request_notes' => $data['request_notes'] ?? null, 'requested_by' => $actor->id, 'requested_at' => now(),
        ]);
        $this->search->reindexPatient($patient->id);
        $this->audit->record('exam.requested', $actor->id, 'exam', $exam->id, ['patient_id' => $patient->id, 'exam_type_id' => $type->id]);

        return $exam;
    }

    public function advance(ExamRequest $exam, string $to, User $actor): void
    {
        if (! in_array($to, $exam->nextSteps(), true)) {
            throw ValidationException::withMessages(['status' => 'Etapa inválida a partir de “'.ExamRequest::STATUS[$exam->status].'”.']);
        }
        $this->moveTo($exam, $to);
        $exam->save();
        $this->search->reindexPatient($exam->patient_id);
        $this->audit->record('exam.status_changed', $actor->id, 'exam', $exam->id, ['to' => $to]);
    }

    /** Registra o resultado (texto e/ou laudo). Marca "recebido" se ainda não estava. */
    public function registerResult(ExamRequest $exam, ?string $text, ?UploadedFile $file, User $actor): void
    {
        if (! $exam->isOpen()) {
            throw ValidationException::withMessages(['result_text' => 'Exame revisado ou cancelado não recebe novo resultado.']);
        }
        if (trim((string) $text) === '' && $file === null && $exam->result_attachment_id === null) {
            throw ValidationException::withMessages(['result_text' => 'Escreva o resultado ou envie o laudo.']);
        }
        DB::transaction(function () use ($exam, $text, $file, $actor) {
            if ($file !== null) {
                $att = $this->attachments->store($file, $exam->patient, null, ['category' => 'exam', 'title' => 'Laudo — '.$exam->type->name, 'description' => null], $actor);
                $exam->result_attachment_id = $att->id;
            }
            if (trim((string) $text) !== '') {
                $exam->result_text = mb_substr(trim((string) $text), 0, 60000);
            }
            $exam->result_by = $actor->id;
            if (array_search($exam->status, ExamRequest::FLOW, true) < array_search('received', ExamRequest::FLOW, true)) {
                $this->moveTo($exam, 'received');
            }
            $exam->save();
        });
        $this->search->reindexPatient($exam->patient_id);
        $this->audit->record('exam.result_registered', $actor->id, 'exam', $exam->id, ['file' => $file !== null]);
    }

    /** Revisão do veterinário: interpretação assinada; o exame fica somente leitura. */
    public function review(ExamRequest $exam, string $interpretation, User $actor): void
    {
        if (! $this->context->can('clinical:sign')) {
            abort(403);
        }
        if ($exam->status !== 'received') {
            throw ValidationException::withMessages(['interpretation' => 'Só é possível revisar com o resultado recebido.']);
        }
        $exam->interpretation = mb_substr(trim($interpretation), 0, 5000);
        $exam->reviewed_by = $actor->id;
        $this->moveTo($exam, 'reviewed');
        $exam->save();
        $this->search->reindexPatient($exam->patient_id);
        $this->audit->record('exam.reviewed', $actor->id, 'exam', $exam->id);
    }

    public function cancel(ExamRequest $exam, string $reason, User $actor): void
    {
        if (! $exam->isOpen()) {
            throw ValidationException::withMessages(['reason' => 'Exame revisado ou cancelado não pode ser cancelado.']);
        }
        $exam->status = 'cancelled';
        $exam->cancelled_at = now();
        $exam->cancel_reason = mb_substr(trim($reason), 0, 200);
        $exam->save();
        $this->search->reindexPatient($exam->patient_id);
        $this->audit->record('exam.cancelled', $actor->id, 'exam', $exam->id);
    }

    /** @return array<string, int> situação => quantidade (painel) */
    public function counts(): array
    {
        return ExamRequest::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    private function moveTo(ExamRequest $exam, string $to): void
    {
        $exam->status = $to;
        foreach (ExamRequest::STAMP as $status => $column) {
            // Etapas puladas ficam sem data; a etapa de destino recebe agora.
            if ($status === $to && $exam->{$column} === null) {
                $exam->{$column} = now();
            }
        }
    }
}
