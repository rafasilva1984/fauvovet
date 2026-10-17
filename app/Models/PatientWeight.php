<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Medida de peso (histórico). Gravada ao finalizar um atendimento com peso informado.
 *
 * @property string $id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $weight_kg
 * @property Carbon $measured_at
 */
class PatientWeight extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['patient_id', 'encounter_id', 'weight_kg', 'measured_at', 'created_by'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }
}
