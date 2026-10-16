<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\Roles;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Convite para entrar na equipe de uma clínica. Só o hash do token é persistido.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property array<int, string> $roles
 * @property string $token_hash
 * @property string $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_by
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by
 * @property-read User $inviter
 */
class Invitation extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const TTL_DAYS = 7;

    protected $fillable = ['email', 'roles', 'token_hash', 'invited_by', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @return list<string> */
    public function roleList(): array
    {
        return array_values(array_filter((array) $this->roles, [Roles::class, 'isRole']));
    }
}
