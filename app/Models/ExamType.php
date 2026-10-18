<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exame do catálogo da clínica. Desativar em vez de apagar (há pedidos ligados).
 *
 * @property string $id
 * @property string $name
 * @property string $category
 * @property string|null $sample
 * @property bool $active
 * @property int|null $analytes_count
 */
class ExamType extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const CATEGORIES = ['lab' => 'Laboratorial', 'imaging' => 'Imagem', 'other' => 'Outro'];

    /** Catálogo inicial GENÉRICO (editável pela clínica): [nome, categoria, material]. */
    public const DEFAULTS = [
        ['Hemograma completo', 'lab', 'Sangue (EDTA)'],
        ['Bioquímica renal (ureia e creatinina)', 'lab', 'Soro'],
        ['Bioquímica hepática (ALT e FA)', 'lab', 'Soro'],
        ['Urinálise', 'lab', 'Urina'],
        ['Coproparasitológico', 'lab', 'Fezes'],
        ['Citologia', 'lab', null],
        ['Ultrassonografia abdominal', 'imaging', null],
        ['Radiografia', 'imaging', null],
        ['Ecocardiograma', 'imaging', null],
    ];

    protected $fillable = ['name', 'category', 'sample', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<ExamAnalyte, $this> */
    public function analytes(): HasMany
    {
        return $this->hasMany(ExamAnalyte::class, 'exam_type_id');
    }
}
