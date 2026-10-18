<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Faixa de referência de um analito para uma espécie ("*" = qualquer espécie), definida pela clínica.
 *
 * @property string $id
 * @property string $analyte_id
 * @property string $species
 * @property string|null $ref_min
 * @property string|null $ref_max
 */
class ExamReferenceRange extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['analyte_id', 'species', 'ref_min', 'ref_max'];
}
