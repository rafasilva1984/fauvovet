<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Model;

/**
 * Tokens de tema por clínica (sem CSS por cliente).
 *
 * @property string $tenant_id
 * @property string $primary_color
 * @property string $secondary_color
 * @property string $mode
 */
class TenantTheme extends Model implements TenantOwned
{
    use BelongsToTenant;

    public const DEFAULT = ['primary' => '#0e5e66', 'secondary' => '#7a4fa3', 'mode' => 'system'];

    protected $primaryKey = 'tenant_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['primary_color', 'secondary_color', 'mode', 'updated_by'];
}
