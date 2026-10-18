<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Grupo de sinônimos da busca clínica DEFINIDO PELA CLÍNICA (uma expressão por linha). Nada vem pronto: o sistema não
 * inventa equivalência clínica (CLAUDE.md §3.3 ex. 6).
 *
 * @property string $id
 * @property string $terms
 * @property string $created_by
 */
class ClinicalSearchSynonym extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['terms', 'created_by', 'updated_by'];

    /** @return list<string> */
    public function lines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\n;,]+/', $this->terms) ?: [])));
    }
}
