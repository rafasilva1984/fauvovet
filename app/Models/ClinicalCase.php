<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Série de casos ou relato de caso montado pela equipe clínica a partir dos próprios atendimentos.
 * Referencia o prontuário; o texto científico (pergunta, resumo, observações) é escrito pelo veterinário.
 *
 * @property string $id
 * @property string $kind
 * @property string $title
 * @property string $question
 * @property string|null $summary
 * @property string|null $specialty
 * @property array<string, mixed>|null $criteria
 * @property string $status
 * @property int $next_label
 * @property string $created_by
 * @property-read Collection<int, ClinicalCasePatient> $entries
 */
class ClinicalCase extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const KINDS = ['series' => 'Série de casos', 'report' => 'Relato de caso'];

    public const STATUS = ['open' => 'Em andamento', 'closed' => 'Encerrado'];

    /** Limite de pacientes por série (mantém exportação e estatísticas leves; refine a busca para séries maiores). */
    public const MAX_PATIENTS = 200;

    protected $fillable = ['kind', 'title', 'question', 'summary', 'specialty', 'criteria', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'next_label' => 'integer'];
    }

    /** @return HasMany<ClinicalCasePatient, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ClinicalCasePatient::class, 'case_id')->orderBy('label');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
