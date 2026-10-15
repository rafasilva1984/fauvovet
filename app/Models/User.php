<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Identidade global (sem tenant_id): a mesma pessoa pode ter vínculo com várias clínicas.
 * Dados de clínica só são acessados via Membership (escopado).
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $crmv
 * @property string $status
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 */
class User extends Authenticatable
{
    use HasUlids;

    protected $fillable = ['name', 'email', 'crmv', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'failed_login_count' => 'integer',
        ];
    }

    /** Sem "lembrar de mim": sessões expiram (ver config/session.php). */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
