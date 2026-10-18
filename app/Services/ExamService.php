<?php

namespace App\Services;

use App\Models\Encounter;
use App\Models\ExamRequest;
use App\Models\ExamType;
use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
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
        if (ExamType::query()->exists()) {
            return;
        }
        DB::transaction(function () {
            foreach (ExamType::DEFAULTS as [$name, $category, $sample]) {
                ExamType::create(['name' => $name, 'category' => $category, 'sample' => $sample, 'active' => true]);
            }
        });
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
