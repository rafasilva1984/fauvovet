<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Analito (parâmetro) de um exame do catálogo, ex.: "Creatinina" em mg/dL. Desativar em vez de apagar.
 *
 * @property string $id
 * @property string $exam_type_id
 * @property string $name
 * @property string|null $unit
 * @property int $position
 * @property bool $active
 * @property-read Collection<int, ExamReferenceRange> $ranges
 */
class ExamAnalyte extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    /**
     * Analitos iniciais (nome e unidade) dos exames do catálogo padrão — SEM faixa de referência: a faixa varia por
     * laboratório, método e espécie e deve ser preenchida pela clínica (ou digitada a partir do laudo).
     */
    public const DEFAULTS = [
        'Hemograma completo' => [['Hemácias', 'milhões/µL'], ['Hemoglobina', 'g/dL'], ['Hematócrito', '%'], ['Leucócitos', '/µL'], ['Plaquetas', '/µL']],
        'Bioquímica renal (ureia e creatinina)' => [['Ureia', 'mg/dL'], ['Creatinina', 'mg/dL']],
        'Bioquímica hepática (ALT e FA)' => [['ALT', 'U/L'], ['Fosfatase alcalina', 'U/L']],
        'Urinálise' => [['Densidade urinária', null], ['pH', null], ['Proteína', null]],
    ];

    protected $fillable = ['exam_type_id', 'name', 'unit', 'position', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    /** @return HasMany<ExamReferenceRange, $this> */
    public function ranges(): HasMany
    {
        return $this->hasMany(ExamReferenceRange::class, 'analyte_id');
    }

    /** Faixa da espécie, senão a de "qualquer espécie". */
    public function rangeFor(string $species): ?ExamReferenceRange
    {
        return $this->ranges->firstWhere('species', $species) ?? $this->ranges->firstWhere('species', '*');
    }
}
