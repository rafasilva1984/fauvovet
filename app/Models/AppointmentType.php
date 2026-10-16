<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de atendimento da clínica (consulta, retorno, vacina...), com duração padrão e cor na agenda.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property int $duration_minutes
 * @property string $color
 * @property bool $active
 */
class AppointmentType extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    /** Tipos iniciais criados para cada clínica (editáveis). Cores com contraste AA para texto branco. */
    public const DEFAULTS = [
        ['Consulta', 30, '#0e5e66'],
        ['Retorno', 20, '#1d4e89'],
        ['Vacinação', 15, '#2f6b2f'],
        ['Exame / coleta', 30, '#6b4c9a'],
        ['Procedimento / cirurgia', 120, '#9a3412'],
        ['Banho e tosa', 60, '#8a3c64'],
    ];

    protected $fillable = ['name', 'duration_minutes', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'duration_minutes' => 'integer'];
    }
}
