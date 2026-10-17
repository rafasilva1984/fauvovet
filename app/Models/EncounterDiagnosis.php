<?php

namespace App\Models;

use App\Clinical\ClinicalRecordLocked;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Hipótese ou diagnóstico registrado num atendimento. Suspeito ≠ confirmado ≠ descartado (CLAUDE.md §12.5).
 * Imutável depois que o atendimento é finalizado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $encounter_id
 * @property string $patient_id
 * @property string $description
 * @property string|null $code
 * @property string $status
 * @property int $position
 */
class EncounterDiagnosis extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const STATUS = ['suspected' => 'Suspeita', 'confirmed' => 'Confirmado', 'ruled_out' => 'Descartado'];

    protected $fillable = ['encounter_id', 'patient_id', 'description', 'code', 'status', 'position', 'created_by'];

    protected static function booted(): void
    {
        $guard = function (EncounterDiagnosis $d) {
            $status = Encounter::query()->whereKey($d->encounter_id)->value('status');
            if ($status === 'finalized') {
                throw new ClinicalRecordLocked('Diagnósticos de atendimento finalizado não podem ser alterados.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}
