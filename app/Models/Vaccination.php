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
 * Aplicação de vacina (carteira). Imutável: correção = anular com motivo e registrar de novo.
 *
 * @property string $id
 * @property string $patient_id
 * @property string $vaccine_id
 * @property string|null $encounter_id
 * @property int $dose_number
 * @property Carbon $applied_on
 * @property string $lot
 * @property string|null $manufacturer
 * @property Carbon|null $lot_expires_on
 * @property string|null $site
 * @property Carbon|null $next_due_on
 * @property string|null $notes
 * @property string $applied_by_membership_id
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property-read Vaccine $vaccine
 * @property-read Patient $patient
 * @property-read Membership $appliedBy
 */
class Vaccination extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const SITES = ['Subcutânea — região interescapular', 'Subcutânea — membro torácico direito', 'Subcutânea — membro torácico esquerdo',
        'Subcutânea — membro pélvico direito', 'Subcutânea — membro pélvico esquerdo', 'Intramuscular', 'Intranasal', 'Oral'];

    private const VOID_FIELDS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected $fillable = ['patient_id', 'vaccine_id', 'encounter_id', 'dose_number', 'applied_on', 'lot', 'manufacturer', 'lot_expires_on',
        'site', 'next_due_on', 'notes', 'applied_by_membership_id', 'created_by'];

    protected function casts(): array
    {
        return ['applied_on' => 'date', 'lot_expires_on' => 'date', 'next_due_on' => 'date', 'voided_at' => 'datetime', 'dose_number' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Vaccination $v) {
            if (array_diff(array_keys($v->getDirty()), self::VOID_FIELDS) !== [] || $v->getOriginal('voided_at') !== null) {
                throw new ClinicalRecordLocked('Aplicação registrada não é alterada; anule e registre de novo.');
            }
        });
        static::deleting(fn () => throw new ClinicalRecordLocked('Aplicações não são excluídas; podem ser anuladas.'));
    }

    /** @return BelongsTo<Vaccine, $this> */
    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'applied_by_membership_id');
    }
}
