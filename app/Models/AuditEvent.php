<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\CrossTenantViolation;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Trilha de auditoria somente-inclusão. Nunca registrar senhas, tokens ou conteúdo clínico bruto.
 *
 * @property int $id
 * @property string|null $tenant_id
 * @property string|null $actor_user_id
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
class AuditEvent extends Model implements TenantOwned
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'actor_user_id', 'action', 'target_type', 'target_id', 'metadata', 'request_id', 'ip_hash'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new CrossTenantViolation('audit_events é somente inclusão.'));
        static::deleting(fn () => throw new CrossTenantViolation('audit_events é somente inclusão.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
