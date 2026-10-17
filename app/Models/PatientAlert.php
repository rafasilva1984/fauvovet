<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Alerta clínico do paciente (alergia, doença crônica, medicação contínua, comportamento). Resolver em vez de apagar.
 *
 * @property string $id
 * @property string $patient_id
 * @property string $kind
 * @property string $severity
 * @property string $description
 * @property Carbon|null $resolved_at
 */
class PatientAlert extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const KINDS = ['allergy' => 'Alergia', 'chronic' => 'Condição crônica', 'medication' => 'Medicação contínua', 'behavior' => 'Comportamento', 'other' => 'Outro'];

    public const SEVERITY = ['high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];

    protected $fillable = ['patient_id', 'kind', 'severity', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
