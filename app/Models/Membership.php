<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\Roles;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo usuário × clínica, com papéis.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property array<int, string> $roles
 * @property string $status
 * @property-read User $user
 */
class Membership extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['user_id', 'roles', 'status'];

    protected function casts(): array
    {
        return ['roles' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> */
    public function roleList(): array
    {
        return array_values(array_filter((array) $this->roles, [Roles::class, 'isRole']));
    }
}
