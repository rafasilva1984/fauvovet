<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Medicamento do catálogo da clínica (ADR-022). Nada vem pré-carregado: doses e faixas são da clínica.
 *
 * @property string $id
 * @property string $name
 * @property string|null $active_ingredient
 * @property string|null $drug_class
 * @property string $form
 * @property string|null $concentration_mg
 * @property string|null $presentation
 * @property string|null $dose_mg_kg
 * @property string|null $dose_min_mg_kg
 * @property string|null $dose_max_mg_kg
 * @property string|null $route
 * @property string|null $frequency
 * @property int|null $duration_days
 * @property array<int, string> $species
 * @property bool $controlled
 * @property string|null $notes
 * @property bool $active
 */
class Medication extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const FORMS = [
        'tablet' => 'Comprimido',
        'capsule' => 'Cápsula',
        'suspension' => 'Suspensão oral',
        'solution' => 'Solução oral',
        'drops' => 'Gotas (solução)',
        'injectable' => 'Injetável',
        'topical' => 'Uso tópico (pomada, creme, spray)',
        'ophthalmic' => 'Oftálmico (colírio, pomada)',
        'otic' => 'Otológico',
        'shampoo' => 'Xampu',
        'other' => 'Outro',
    ];

    /** Formas com cálculo de quantidade: [singular, plural, passo de arredondamento]. Concentração em mg por unidade. */
    public const UNITS = [
        'tablet' => ['comprimido', 'comprimidos', 0.25],
        'capsule' => ['cápsula', 'cápsulas', 1.0],
        'suspension' => ['mL', 'mL', 0.1],
        'solution' => ['mL', 'mL', 0.1],
        'drops' => ['mL', 'mL', 0.05],
        'injectable' => ['mL', 'mL', 0.01],
    ];

    public const ROUTES = ['Oral', 'Subcutânea', 'Intramuscular', 'Intravenosa', 'Tópica', 'Oftálmica', 'Otológica', 'Retal', 'Inalatória', 'Transdérmica', 'Intranasal'];

    public const FREQUENCIES = ['Dose única', 'A cada 4 horas', 'A cada 6 horas', 'A cada 8 horas', 'A cada 12 horas', 'A cada 24 horas',
        'A cada 48 horas', 'A cada 72 horas', 'Uma vez por semana', 'Se necessário'];

    protected $fillable = ['name', 'active_ingredient', 'drug_class', 'form', 'concentration_mg', 'presentation', 'dose_mg_kg', 'dose_min_mg_kg',
        'dose_max_mg_kg', 'route', 'frequency', 'duration_days', 'species', 'controlled', 'notes', 'active', 'updated_by'];

    protected function casts(): array
    {
        return ['species' => 'array', 'controlled' => 'boolean', 'active' => 'boolean', 'duration_days' => 'integer'];
    }

    public function appliesTo(string $species): bool
    {
        return $this->species === [] || in_array($species, $this->species, true);
    }

    /** Rótulo da unidade de concentração para a forma ("mg/comprimido", "mg/mL") ou null quando não há cálculo. */
    public static function concentrationUnit(?string $form): ?string
    {
        return isset(self::UNITS[(string) $form]) ? 'mg/'.self::UNITS[(string) $form][0] : null;
    }

    /** "comprimido 250 mg", "suspensão oral 50 mg/mL" (apresentação padrão quando a clínica não escreveu uma). */
    public function defaultPresentation(): string
    {
        $conc = '';
        if ($this->concentration_mg !== null && isset(self::UNITS[$this->form])) {
            $conc = ' '.self::num((float) $this->concentration_mg).(in_array($this->form, ['tablet', 'capsule'], true) ? ' mg' : ' mg/mL');
        }

        return mb_strtolower(self::FORMS[$this->form] ?? $this->form).$conc;
    }

    /** "Amoxicilina — comprimido 250 mg" (para a lista do formulário). */
    public function label(): string
    {
        return $this->name.' — '.($this->presentation ?: $this->defaultPresentation());
    }

    /** Número no formato brasileiro, sem zeros à direita. */
    public static function num(float $v, int $decimals = 4): string
    {
        return rtrim(rtrim(number_format($v, $decimals, ',', '.'), '0'), ',');
    }
}
