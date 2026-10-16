<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Agendamento. starts_at/ends_at em UTC; a tela converte para o fuso da unidade.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $patient_id
 * @property string $appointment_type_id
 * @property string|null $professional_membership_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property bool $is_walk_in
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read Unit $unit
 * @property-read Patient $patient
 * @property-read AppointmentType $type
 * @property-read Membership|null $professional
 */
class Appointment extends Model implements TenantOwned
{
    use BelongsToTenant, HasUlids;

    public const STATUS = [
        'scheduled' => 'Agendado',
        'confirmed' => 'Confirmado',
        'waiting' => 'Aguardando',
        'in_progress' => 'Em atendimento',
        'completed' => 'Concluído',
        'no_show' => 'Faltou',
        'cancelled' => 'Cancelado',
    ];

    /** Status que ocupam a agenda do profissional (entram na checagem de conflito). */
    public const ACTIVE = ['scheduled', 'confirmed', 'waiting', 'in_progress', 'completed'];

    /** Transições permitidas: de → [para]. Qualquer outra é recusada. */
    public const TRANSITIONS = [
        'scheduled' => ['confirmed', 'waiting', 'no_show', 'cancelled'],
        'confirmed' => ['waiting', 'no_show', 'cancelled'],
        'waiting' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => [],
        'no_show' => ['scheduled'],
        'cancelled' => ['scheduled'],
    ];

    /** Transições do atendimento em si (exigem permissão schedule:attend). */
    public const ATTEND = ['in_progress', 'completed'];

    /** Rótulo do botão de cada transição. */
    public const ACTION_LABELS = [
        'confirmed' => 'Confirmar',
        'waiting' => 'Registrar chegada',
        'in_progress' => 'Iniciar atendimento',
        'completed' => 'Concluir',
        'no_show' => 'Faltou',
        'cancelled' => 'Cancelar',
        'scheduled' => 'Reabrir',
    ];

    protected $fillable = ['unit_id', 'patient_id', 'appointment_type_id', 'professional_membership_id', 'starts_at', 'ends_at', 'status', 'is_walk_in', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_walk_in' => 'boolean',
            'confirmed_at' => 'datetime', 'checked_in_at' => 'datetime', 'started_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<AppointmentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    /** @return BelongsTo<Membership, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'professional_membership_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['completed'], true);
    }
}
