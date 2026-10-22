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
 * Item de uma receita emitida. Imutável: corrigir = anular a receita (documento) e emitir outra.
 *
 * @property string $id
 * @property string $document_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string|null $medication_id
 * @property int $position
 * @property string $name
 * @property string|null $form
 * @property string|null $presentation
 * @property string|null $concentration_mg
 * @property string|null $weight_kg
 * @property string|null $dose_mg_kg
 * @property string|null $dose_mg
 * @property string|null $quantity
 * @property string $dose_text
 * @property string $route
 * @property string $frequency
 * @property int|null $duration_days
 * @property bool $continuous
 * @property string|null $instructions
 * @property bool $controlled
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon $created_at
 * @property-read IssuedDocument $document
 */
class PrescriptionItem extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'patient_id', 'encounter_id', 'medication_id', 'position', 'name', 'form', 'presentation', 'concentration_mg',
        'weight_kg', 'dose_mg_kg', 'dose_mg', 'quantity', 'dose_text', 'route', 'frequency', 'duration_days', 'continuous', 'instructions',
        'controlled', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'continuous' => 'boolean', 'controlled' => 'boolean', 'position' => 'integer', 'duration_days' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ClinicalRecordLocked('Item de receita emitida não é alterado; anule a receita e emita outra.'));
        static::deleting(fn () => throw new ClinicalRecordLocked('Itens de receita não são excluídos; a receita pode ser anulada.'));
    }

    /** @return BelongsTo<IssuedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(IssuedDocument::class, 'document_id');
    }
}
