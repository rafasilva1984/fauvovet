<?php

namespace App\Models;

use App\Clinical\ClinicalRecordLocked;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Adendo a atendimento finalizado: correção/complemento datado e assinado. Somente inclusão.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $encounter_id
 * @property string $body
 * @property string $author_id
 * @property string $author_membership_id
 * @property Carbon $created_at
 * @property-read User $author
 */
class EncounterAddendum extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'encounter_addenda';

    protected $fillable = ['encounter_id', 'body', 'author_id', 'author_membership_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new ClinicalRecordLocked('Adendos são somente inclusão.'));
        static::deleting(fn () => throw new ClinicalRecordLocked('Adendos são somente inclusão.'));
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
