<?php

namespace App\Models;

use App\Clinical\ClinicalRecordLocked;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pedido de exame (CLAUDE.md §2.1 F): solicitado → coletado → em análise → recebido → revisado (ou cancelado).
 * Revisado é registro clínico assinado: não muda mais; pedidos não são excluídos.
 *
 * @property string $id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $exam_type_id
 * @property string $status
 * @property string|null $lab_name
 * @property string|null $request_notes
 * @property string $requested_by
 * @property Carbon $requested_at
 * @property Carbon|null $collected_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property string|null $result_text
 * @property string|null $result_attachment_id
 * @property string|null $result_by
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property string|null $interpretation
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read Patient $patient
 * @property-read ExamType $type
 * @property-read User $requester
 * @property-read User|null $reviewer
 * @property-read Attachment|null $resultAttachment
 * @property-read Collection<int, ExamResultValue> $values
 * @property int|null $out_of_range_count
 */
class ExamRequest extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const STATUS = ['requested' => 'Solicitado', 'collected' => 'Coletado', 'in_analysis' => 'Em análise', 'received' => 'Resultado recebido', 'reviewed' => 'Revisado', 'cancelled' => 'Cancelado'];

    /** Ordem do fluxo (cancelado fica fora). */
    public const FLOW = ['requested', 'collected', 'in_analysis', 'received', 'reviewed'];

    /** Coluna de data/hora de cada etapa. */
    public const STAMP = ['collected' => 'collected_at', 'in_analysis' => 'sent_at', 'received' => 'received_at', 'reviewed' => 'reviewed_at'];

    public const TONE = ['requested' => 'neutral', 'collected' => 'tint', 'in_analysis' => 'warning', 'received' => 'danger', 'reviewed' => 'success', 'cancelled' => 'neutral'];

    protected $fillable = ['patient_id', 'encounter_id', 'exam_type_id', 'lab_name', 'request_notes', 'requested_by', 'requested_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'collected_at' => 'datetime', 'sent_at' => 'datetime', 'received_at' => 'datetime',
            'reviewed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (ExamRequest $e) {
            if (in_array($e->getOriginal('status'), ['reviewed', 'cancelled'], true)) {
                throw new ClinicalRecordLocked('Exame revisado ou cancelado não é alterado.');
            }
        });
        static::deleting(fn () => throw new ClinicalRecordLocked('Pedidos de exame não são excluídos; podem ser cancelados.'));
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['reviewed', 'cancelled'], true);
    }

    /**
     * Próximas etapas possíveis a partir da atual (pode pular etapas; revisar exige resultado e é ação própria).
     *
     * @return list<string>
     */
    public function nextSteps(): array
    {
        $at = array_search($this->status, self::FLOW, true);

        return $this->isOpen() && $at !== false ? array_values(array_filter(array_slice(self::FLOW, $at + 1), fn ($s) => $s !== 'reviewed')) : [];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<ExamType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ExamResultValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ExamResultValue::class, 'exam_request_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function resultAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'result_attachment_id');
    }
}
