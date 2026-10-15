<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $timezone
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $state
 */
class Unit extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['name', 'timezone', 'address_line', 'city', 'state'];
}
