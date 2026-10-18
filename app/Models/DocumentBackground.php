<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fundo de documento da clínica (papel timbrado ou receituário). O original enviado fica preservado (hash) e o
 * "fundo pronto" é um PDF A4 de uma página sobre o qual o texto do documento é impresso (ADR-020).
 * Trocar o fundo aposenta o anterior: nada é apagado e os documentos emitidos não mudam.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $source_name
 * @property string $source_mime
 * @property string $source_path
 * @property string $source_sha256
 * @property int $source_bytes
 * @property string $render_path
 * @property string $render_sha256
 * @property int $margin_top
 * @property int $margin_bottom
 * @property int $margin_side
 * @property string|null $conversion_notes
 * @property string $uploaded_by
 * @property Carbon $uploaded_at
 * @property Carbon|null $retired_at
 * @property string|null $retired_by
 */
class DocumentBackground extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const KINDS = ['letterhead' => 'Papel timbrado', 'prescription' => 'Receituário'];

    /** Opção de fundo de cada modelo/documento. */
    public const CHOICES = [
        'auto' => 'Automático (receituário nas receitas, timbrado nos demais)',
        'letterhead' => 'Papel timbrado',
        'prescription' => 'Receituário',
        'none' => 'Sem fundo (cabeçalho padrão do FauvoVet)',
    ];

    /** Margens sugeridas (mm) para PDF/imagem, onde não há como saber onde o timbre termina. */
    public const DEFAULT_MARGINS = ['top' => 40, 'bottom' => 30, 'side' => 20];

    protected $fillable = ['kind', 'margin_top', 'margin_bottom', 'margin_side'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime', 'retired_at' => 'datetime', 'source_bytes' => 'integer',
            'margin_top' => 'integer', 'margin_bottom' => 'integer', 'margin_side' => 'integer'];
    }

    /**
     * Opções do campo "Fundo": tipos ainda não enviados aparecem marcados (caem no automático até o envio).
     *
     * @param  array<string, DocumentBackground>  $current
     * @return array<string, string>
     */
    public static function options(array $current): array
    {
        $out = [];
        foreach (self::CHOICES as $key => $label) {
            $out[$key] = isset(self::KINDS[$key]) && ! isset($current[$key]) ? $label.' — ainda não enviado' : $label;
        }

        return $out;
    }

    /** @param Builder<DocumentBackground> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('retired_at');
    }

    public function sizeLabel(): string
    {
        return $this->source_bytes >= 1048576 ? number_format($this->source_bytes / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->source_bytes / 1024)).' KB';
    }
}
