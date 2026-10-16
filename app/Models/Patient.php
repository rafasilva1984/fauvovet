<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Paciente (animal) de uma clínica. Sem dados clínicos aqui (ficam no prontuário, com permissão clínica).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $species
 * @property string|null $species_detail
 * @property string|null $breed
 * @property string $sex
 * @property string $neutered
 * @property Carbon|null $birth_date
 * @property bool $birth_date_estimated
 * @property string|null $coat
 * @property string|null $microchip
 * @property string|null $marks
 * @property string $status
 * @property Carbon|null $deceased_on
 * @property-read Collection<int, Guardian> $guardians
 */
class Patient extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const SEX = ['male' => 'Macho', 'female' => 'Fêmea', 'unknown' => 'Não informado'];

    public const NEUTERED = ['yes' => 'Castrado(a)', 'no' => 'Não castrado(a)', 'unknown' => 'Não informado'];

    public const STATUS = ['active' => 'Ativo', 'deceased' => 'Óbito', 'archived' => 'Arquivado'];

    public const RELATIONSHIPS = ['primary' => 'Responsável principal', 'co_guardian' => 'Co-responsável', 'authorized' => 'Autorizado(a)'];

    protected $fillable = ['name', 'species', 'species_detail', 'breed', 'sex', 'neutered', 'birth_date', 'birth_date_estimated', 'coat', 'microchip', 'marks', 'status', 'deceased_on', 'created_by'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'deceased_on' => 'date', 'birth_date_estimated' => 'boolean'];
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_patient')
            ->withPivot(['tenant_id', 'relationship'])->withTimestamps();
    }

    /** Idade legível ("3 anos e 2 meses", "5 meses", "12 dias"); prefixo "~" quando a data é estimada. */
    public function ageLabel(?Carbon $at = null): ?string
    {
        if ($this->birth_date === null) {
            return null;
        }
        $end = $this->deceased_on ?? $at ?? now();
        $diff = $this->birth_date->diff($end);
        $parts = match (true) {
            $diff->y > 0 => $diff->y.' '.($diff->y === 1 ? 'ano' : 'anos').($diff->m > 0 ? ' e '.$diff->m.' '.($diff->m === 1 ? 'mês' : 'meses') : ''),
            $diff->m > 0 => $diff->m.' '.($diff->m === 1 ? 'mês' : 'meses'),
            default => $diff->d.' '.($diff->d === 1 ? 'dia' : 'dias'),
        };

        return ($this->birth_date_estimated ? '~' : '').$parts;
    }
}
