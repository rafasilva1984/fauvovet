<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesquisa salva da busca clínica (termo + filtros). Privada de quem salvou, ou compartilhada com a clínica.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property array<string, string> $params
 * @property bool $shared
 * @property-read User $user
 */
class SavedSearch extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const PARAMS = ['q', 'especie', 'periodo', 'origem', 'situacao', 'ordem'];

    protected $fillable = ['user_id', 'name', 'params', 'shared'];

    protected function casts(): array
    {
        return ['params' => 'array', 'shared' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
