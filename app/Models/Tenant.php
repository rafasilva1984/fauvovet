<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Clínica/profissional. O escopo usa a própria chave: só a clínica ativa é visível.
 *
 * @property string $id
 * @property string $trade_name
 * @property string|null $legal_name
 * @property string $person_type
 * @property string|null $document_number
 * @property string $status
 */
class Tenant extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['trade_name', 'legal_name', 'person_type', 'document_number'];

    public function tenantKeyColumn(): string
    {
        return 'id';
    }

    /** @return HasOne<TenantTheme, $this> */
    public function theme(): HasOne
    {
        return $this->hasOne(TenantTheme::class, 'tenant_id');
    }
}
