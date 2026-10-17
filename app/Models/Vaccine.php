<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Vacina do catálogo da clínica (protocolo: doses da série, intervalo e reforço).
 *
 * @property string $id
 * @property string $name
 * @property array<int, string> $species
 * @property string|null $manufacturer
 * @property int $series_doses
 * @property int $series_interval_days
 * @property int|null $booster_interval_days
 * @property string|null $notes
 * @property bool $active
 */
class Vaccine extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    /**
     * Modelos iniciais GENÉRICOS (editáveis): a clínica deve revisar com o próprio protocolo e a bula.
     * [nome, espécies, doses da série, intervalo (dias), reforço (dias)]
     */
    public const DEFAULTS = [
        ['Polivalente canina (V8/V10)', ['canine'], 3, 21, 365],
        ['Antirrábica', ['canine', 'feline'], 1, 0, 365],
        ['Tosse dos canis (traqueobronquite)', ['canine'], 2, 21, 365],
        ['Giardíase', ['canine'], 2, 21, 365],
        ['Leishmaniose visceral canina', ['canine'], 3, 21, 365],
        ['Polivalente felina (V4/V5)', ['feline'], 3, 21, 365],
        ['Leucemia felina (FeLV)', ['feline'], 2, 21, 365],
    ];

    protected $fillable = ['name', 'species', 'manufacturer', 'series_doses', 'series_interval_days', 'booster_interval_days', 'notes', 'active'];

    protected function casts(): array
    {
        return ['species' => 'array', 'active' => 'boolean', 'series_doses' => 'integer', 'series_interval_days' => 'integer', 'booster_interval_days' => 'integer'];
    }

    public function appliesTo(string $species): bool
    {
        return $this->species === [] || in_array($species, $this->species, true);
    }
}
