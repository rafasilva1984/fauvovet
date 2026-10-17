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
 * Documento emitido: texto final + PDF preservados. Nunca reescrito nem apagado — só ANULADO.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string|null $template_id
 * @property int|null $template_version
 * @property string $category
 * @property string $title
 * @property string $body
 * @property string $pdf_path
 * @property string $pdf_sha256
 * @property int $pdf_bytes
 * @property string $verification_code
 * @property string $issued_by
 * @property string $issuer_membership_id
 * @property Carbon $issued_at
 * @property Carbon|null $voided_at
 * @property string|null $voided_by
 * @property string|null $void_reason
 * @property-read Patient $patient
 * @property-read User $issuer
 * @property-read Encounter|null $encounter
 */
class IssuedDocument extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    /** Únicos campos que podem mudar depois da emissão (anulação). */
    private const VOID_FIELDS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected $fillable = ['patient_id', 'encounter_id', 'template_id', 'template_version', 'category', 'title', 'body', 'pdf_path',
        'pdf_sha256', 'pdf_bytes', 'verification_code', 'issued_by', 'issuer_membership_id', 'issued_at'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'voided_at' => 'datetime', 'pdf_bytes' => 'integer', 'template_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (IssuedDocument $d) {
            if (array_diff(array_keys($d->getDirty()), self::VOID_FIELDS) !== [] || $d->getOriginal('voided_at') !== null) {
                throw new ClinicalRecordLocked('Documento emitido não pode ser alterado. Anule e emita outro.');
            }
        });
        static::deleting(fn () => throw new ClinicalRecordLocked('Documentos emitidos não são excluídos; podem ser anulados.'));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }
}
