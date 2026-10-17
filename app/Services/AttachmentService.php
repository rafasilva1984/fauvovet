<?php

namespace App\Services;

use App\Files\FileTypeDetector;
use App\Files\TextExtractor;
use App\Models\Attachment;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anexos do prontuário em disco PRIVADO (storage/app/private/attachments/{clínica}/{ano}/{id}.{ext}).
 * Tipo conferido pelo conteúdo; nome no disco aleatório; SHA-256 conferido ao baixar; nada é executado.
 */
final class AttachmentService
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit, private readonly ClinicalSearchIndex $search) {}

    /** @param array{category: string, title: ?string, description: ?string} $data */
    public function store(UploadedFile $file, Patient $patient, ?Encounter $encounter, array $data, User $actor): Attachment
    {
        if ($encounter && $encounter->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['file' => 'O atendimento é de outro paciente.']);
        }
        $original = FileTypeDetector::safeName($file->getClientOriginalName());
        $type = FileTypeDetector::detect((string) $file->getRealPath(), $original);
        if ($type === null) {
            throw ValidationException::withMessages(['file' => 'Tipo de arquivo não aceito. Envie PDF, JPG, PNG, WebP ou DOCX (o conteúdo precisa corresponder à extensão).']);
        }

        $id = (string) Str::ulid();
        $path = sprintf('attachments/%s/%s/%s.%s', $this->context->requireId(), now()->format('Y'), $id, $type['extension']);
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        Storage::disk('local')->put($path, $bytes);

        try {
            $attachment = new Attachment([
                'patient_id' => $patient->id, 'encounter_id' => $encounter?->id, 'category' => $data['category'],
                'title' => trim((string) ($data['title'] ?? '')) ?: pathinfo($original, PATHINFO_FILENAME),
                'description' => $data['description'] ?? null, 'original_name' => $original, 'mime' => $type['mime'],
                'extension' => $type['extension'], 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                'storage_path' => $path, 'uploaded_by' => $actor->id,
            ]);
            $attachment->id = $id;
            $attachment->save();
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }
        $this->extractText($attachment, $bytes);
        $this->search->reindexPatient($patient->id);
        $this->audit->record('attachment.uploaded', $actor->id, 'attachment', $id, ['patient_id' => $patient->id, 'mime' => $type['mime'], 'bytes' => strlen($bytes), 'text' => $attachment->text_status]);

        return $attachment;
    }

    /** Conteúdo com conferência de integridade. Anexo removido não é entregue. */
    public function contents(Attachment $attachment): string
    {
        abort_if($attachment->removed_at !== null, 404);
        $bytes = Storage::disk('local')->get($attachment->storage_path);
        if ($bytes === null || ! hash_equals($attachment->sha256, hash('sha256', $bytes))) {
            abort(500, 'Arquivo do anexo ausente ou alterado.');
        }

        return $bytes;
    }

    public function remove(Attachment $attachment, string $reason, User $actor): void
    {
        if ($attachment->removed_at !== null) {
            return;
        }
        if ($attachment->uploaded_by !== $actor->id && ! in_array('tenant_owner', $this->context->roles(), true)) {
            abort(403);
        }
        $attachment->removed_at = now();
        $attachment->removed_by = $actor->id;
        $attachment->removed_reason = mb_substr($reason, 0, 200);
        $attachment->save();
        $this->search->reindexPatient($attachment->patient_id);
        $this->audit->record('attachment.removed', $actor->id, 'attachment', $attachment->id);
    }

    /** Lê o texto do anexo (PDF/DOCX) para a busca; imagem fica "sem texto pesquisável". Não reindexa sozinho. */
    public function extractText(Attachment $attachment, ?string $bytes = null): void
    {
        $bytes ??= (string) Storage::disk('local')->get($attachment->storage_path);
        $result = TextExtractor::extract($bytes, $attachment->extension);
        $attachment->text_status = $result['status'];
        $attachment->extracted_text = $result['text'];
        $attachment->text_chars = $result['text'] !== null ? mb_strlen($result['text']) : 0;
        $attachment->text_extracted_at = now();
        $attachment->save();
    }

    /** Anexos enviados antes da leitura de texto existir (text_status nulo). */
    public function pendingCount(): int
    {
        return Attachment::query()->whereNull('removed_at')->whereNull('text_status')->count();
    }

    /** Lê até $limit anexos pendentes e reindexa os pacientes afetados. @return int quantos foram lidos */
    public function extractPending(int $limit = 30): int
    {
        $list = Attachment::query()->whereNull('removed_at')->whereNull('text_status')->orderBy('created_at')->limit($limit)->get();
        foreach ($list as $a) {
            $this->extractText($a);
        }
        foreach ($list->pluck('patient_id')->unique() as $patientId) {
            $this->search->reindexPatient((string) $patientId);
        }

        return $list->count();
    }
}
