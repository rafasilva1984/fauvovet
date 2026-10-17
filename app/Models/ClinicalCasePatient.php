<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paciente incluído num caso científico, com pseudônimo, atendimento de referência, desfecho e consentimento do tutor.
 *
 * @property string $id
 * @property string $case_id
 * @property string $patient_id
 * @property string|null $encounter_id
 * @property string $label
 * @property string $outcome
 * @property string|null $notes
 * @property string $consent
 * @property Carbon|null $consent_on
 * @property string|null $consent_recorded_by
 * @property-read Patient $patient
 * @property-read Encounter|null $encounter
 */
class ClinicalCasePatient extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const OUTCOMES = [
        'unknown' => 'Não avaliado', 'resolved' => 'Resolvido', 'improved' => 'Melhora', 'stable' => 'Estável',
        'worsened' => 'Piora', 'deceased' => 'Óbito', 'lost' => 'Perda de seguimento',
    ];

    public const CONSENT = ['pending' => 'Pendente', 'granted' => 'Autorizado', 'refused' => 'Recusado'];

    protected $fillable = ['case_id', 'patient_id', 'encounter_id', 'label', 'outcome', 'notes', 'consent', 'consent_on', 'consent_recorded_by', 'added_by'];

    protected function casts(): array
    {
        return ['consent_on' => 'date'];
    }

    /** @return BelongsTo<ClinicalCase, $this> */
    public function clinicalCase(): BelongsTo
    {
        return $this->belongsTo(ClinicalCase::class, 'case_id');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }
}
