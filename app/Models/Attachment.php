<?php

namespace App\Models;

use App\Clinical\ClinicalRecordLocked;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anexo do prontuário. Imutável depois do envio (só pode ser "removido" — ocultado com motivo; o arquivo fica).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $category
 * @property string $title
 * @property string|null $description
 * @property string $original_name
 * @property string $mime
 * @property string $extension
 * @property int $size_bytes
 * @property string $sha256
 * @property string $storage_path
 * @property string $uploaded_by
 * @property Carbon|null $removed_at
 * @property string|null $removed_by
 * @property string|null $removed_reason
 * @property string|null $text_status
 * @property string|null $extracted_text
 * @property int|null $text_chars
 * @property Carbon|null $text_extracted_at
 * @property Carbon $created_at
 * @property-read User $uploader
 */
class Attachment extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const CATEGORIES = ['exam' => 'Exame', 'image' => 'Imagem', 'document' => 'Documento', 'other' => 'Outro'];

    private const REMOVE_FIELDS = ['removed_at', 'removed_by', 'removed_reason', 'updated_at'];

    /** Texto extraído é derivado (o arquivo original não muda): pode ser gravado/relido. */
    private const TEXT_FIELDS = ['text_status', 'extracted_text', 'text_chars', 'text_extracted_at', 'updated_at'];

    protected $fillable = ['patient_id', 'encounter_id', 'category', 'title', 'description', 'original_name', 'mime', 'extension',
        'size_bytes', 'sha256', 'storage_path', 'uploaded_by'];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime', 'size_bytes' => 'integer', 'text_chars' => 'integer', 'text_extracted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Attachment $a) {
            $dirty = array_keys($a->getDirty());
            if (array_diff($dirty, self::TEXT_FIELDS) === []) {
                return;
            }
            if (array_diff($dirty, self::REMOVE_FIELDS) !== [] || $a->getOriginal('removed_at') !== null) {
                throw new ClinicalRecordLocked('Anexo enviado não é alterado; remova e envie outro.');
            }
        });
        static::deleting(fn () => throw new ClinicalRecordLocked('Anexos não são excluídos; podem ser removidos da ficha.'));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function sizeLabel(): string
    {
        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->size_bytes / 1024)).' KB';
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
