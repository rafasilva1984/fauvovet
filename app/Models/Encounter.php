<?php

namespace App\Models;

use App\Clinical\ClinicalRecordLocked;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Atendimento (registro clínico). Rascunho editável; FINALIZADO é imutável (o model recusa qualquer alteração
 * de conteúdo e a exclusão) — correções viram adendos.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $patient_id
 * @property string|null $appointment_id
 * @property string|null $appointment_type_id
 * @property string $status
 * @property string|null $chief_complaint
 * @property string|null $history
 * @property string|null $physical_exam
 * @property string|null $plan
 * @property string|null $guidance
 * @property string|null $weight_kg
 * @property string|null $temperature_c
 * @property int|null $heart_rate
 * @property int|null $respiratory_rate
 * @property string|null $capillary_refill_s
 * @property string|null $mucous_membranes
 * @property string|null $hydration
 * @property int|null $body_condition_score
 * @property int|null $pain_score
 * @property Carbon $started_at
 * @property Carbon|null $finalized_at
 * @property string|null $finalized_by
 * @property string|null $signer_membership_id
 * @property string|null $created_by
 * @property-read Patient $patient
 * @property-read Unit $unit
 * @property-read Appointment|null $appointment
 * @property-read AppointmentType|null $type
 * @property-read User|null $author
 * @property-read User|null $signer
 * @property-read Collection<int, EncounterDiagnosis> $diagnoses
 * @property-read Collection<int, EncounterAddendum> $addenda
 */
class Encounter extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    /** Campos clínicos de texto livre, na ordem da ficha. */
    public const TEXT_FIELDS = [
        'chief_complaint' => 'Queixa principal',
        'history' => 'Anamnese e histórico',
        'physical_exam' => 'Exame físico',
        'plan' => 'Conduta e plano terapêutico',
        'guidance' => 'Orientações ao tutor',
    ];

    /** Sinais vitais: campo => [rótulo, unidade]. */
    public const VITALS = [
        'weight_kg' => ['Peso', 'kg'],
        'temperature_c' => ['Temperatura', '°C'],
        'heart_rate' => ['Freq. cardíaca', 'bpm'],
        'respiratory_rate' => ['Freq. respiratória', 'mpm'],
        'capillary_refill_s' => ['TPC', 's'],
        'body_condition_score' => ['Escore corporal', '/9'],
        'pain_score' => ['Escala de dor', '/10'],
        'mucous_membranes' => ['Mucosas', ''],
        'hydration' => ['Hidratação', ''],
    ];

    public const CONTENT = [
        'chief_complaint', 'history', 'physical_exam', 'plan', 'guidance', 'weight_kg', 'temperature_c', 'heart_rate',
        'respiratory_rate', 'capillary_refill_s', 'mucous_membranes', 'hydration', 'body_condition_score', 'pain_score',
    ];

    protected $fillable = [
        'unit_id', 'patient_id', 'appointment_id', 'appointment_type_id', 'status', 'started_at', 'created_by', 'updated_by',
        ...self::CONTENT,
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finalized_at' => 'datetime', 'heart_rate' => 'integer', 'respiratory_rate' => 'integer',
            'body_condition_score' => 'integer', 'pain_score' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (Encounter $e) {
            if ($e->getOriginal('status') === 'finalized') {
                throw new ClinicalRecordLocked('Atendimento finalizado não pode ser alterado. Use um adendo.');
            }
        });
        static::deleting(fn () => throw new ClinicalRecordLocked('Atendimentos não são excluídos.'));
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<AppointmentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /** @return HasMany<EncounterDiagnosis, $this> */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class)->orderBy('position');
    }

    /** @return HasMany<EncounterAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(EncounterAddendum::class)->orderBy('created_at');
    }
}
