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
 * Valor de um analito em um pedido de exame, com a unidade e a faixa USADAS naquele resultado (cópia). A situação
 * (abaixo/dentro/acima) é só a comparação com a faixa informada — não é interpretação clínica.
 * Depois que o exame é revisado, o serviço não altera mais os valores.
 *
 * @property string $id
 * @property string $exam_request_id
 * @property string $patient_id
 * @property string $analyte_id
 * @property string|null $value
 * @property string|null $value_text
 * @property string|null $unit
 * @property string|null $ref_min
 * @property string|null $ref_max
 * @property string $flag
 * @property Carbon $measured_at
 * @property string $recorded_by
 * @property-read ExamAnalyte $analyte
 */
class ExamResultValue extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const FLAGS = ['low' => 'Abaixo da faixa', 'normal' => 'Dentro da faixa', 'high' => 'Acima da faixa', 'none' => 'Sem faixa'];

    public const TONE = ['low' => 'warning', 'normal' => 'success', 'high' => 'danger', 'none' => 'neutral'];

    protected $fillable = ['exam_request_id', 'patient_id', 'analyte_id', 'value', 'value_text', 'unit', 'ref_min', 'ref_max', 'flag', 'measured_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new ClinicalRecordLocked('Valores de exame não são excluídos; corrija enquanto o exame não foi revisado.'));
    }

    /** @return BelongsTo<ExamAnalyte, $this> */
    public function analyte(): BelongsTo
    {
        return $this->belongsTo(ExamAnalyte::class, 'analyte_id');
    }

    public static function flagFor(?float $value, ?float $min, ?float $max): string
    {
        if ($value === null || ($min === null && $max === null)) {
            return 'none';
        }

        return $min !== null && $value < $min ? 'low' : ($max !== null && $value > $max ? 'high' : 'normal');
    }

    /** Número no formato brasileiro, sem zeros à direita. */
    public static function fmt(string|float|null $v): string
    {
        return $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }

    public function display(): string
    {
        return $this->value !== null ? self::fmt($this->value) : (string) $this->value_text;
    }

    public function rangeLabel(): string
    {
        return match (true) {
            $this->ref_min !== null && $this->ref_max !== null => self::fmt($this->ref_min).' – '.self::fmt($this->ref_max),
            $this->ref_min !== null => '≥ '.self::fmt($this->ref_min),
            $this->ref_max !== null => '≤ '.self::fmt($this->ref_max),
            default => '—',
        };
    }
}
