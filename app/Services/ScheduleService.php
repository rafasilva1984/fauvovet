<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Membership;
use App\Models\Unit;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras da agenda da clínica ativa: fuso por unidade (grava UTC), unidades permitidas ao vínculo, conflito de
 * horário por profissional (encaixe pode sobrepor), máquina de status e auditoria.
 */
final class ScheduleService
{
    /** Papéis que atendem e por isso aparecem como "profissional" na agenda. */
    public const PROFESSIONAL_ROLES = ['veterinarian', 'clinical_assistant', 'pet_services'];

    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    /** Cria os tipos de atendimento iniciais na primeira vez que a clínica usa a agenda. */
    public function ensureDefaultTypes(): void
    {
        if (AppointmentType::query()->exists()) {
            return;
        }
        DB::transaction(function () {
            foreach (AppointmentType::DEFAULTS as [$name, $minutes, $color]) {
                AppointmentType::create(['name' => $name, 'duration_minutes' => $minutes, 'color' => $color, 'active' => true]);
            }
        });
    }

    /**
     * Unidades em que o vínculo ativo pode agendar: a unidade escolhida no contexto, ou as permitidas ao vínculo
     * (sem restrição = todas da clínica).
     *
     * @return Collection<int, Unit>
     */
    public function allowedUnits(): Collection
    {
        $tenantId = $this->context->requireId();
        $restricted = DB::table('membership_units')->where('tenant_id', $tenantId)
            ->where('membership_id', $this->context->membership()?->id)->pluck('unit_id')->all();

        return Unit::query()
            ->when($this->context->unitId() !== null, fn ($q) => $q->whereKey($this->context->unitId()))
            ->when($this->context->unitId() === null && $restricted !== [], fn ($q) => $q->whereIn('id', $restricted))
            ->orderBy('name')->get();
    }

    /** @return Collection<int, Membership> vínculos ativos com papel de atendimento, ordenados pelo nome */
    public function professionals(): Collection
    {
        return Membership::query()->where('status', 'active')->with('user:id,name')->get()
            ->filter(fn (Membership $m) => array_intersect($m->roleList(), self::PROFESSIONAL_ROLES) !== [])
            ->sortBy(fn (Membership $m) => mb_strtolower($m->user->name))->values();
    }

    public static function toUtc(string $date, string $time, string $timezone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone)->utc();
    }

    /**
     * @param  array{unit_id: string, patient_id: string, appointment_type_id: string, professional_membership_id: ?string, starts_at: Carbon, ends_at: Carbon, is_walk_in: bool, notes: ?string}  $attrs
     */
    public function create(array $attrs, string $actorId): Appointment
    {
        return DB::transaction(function () use ($attrs, $actorId) {
            $this->lockProfessional($attrs['professional_membership_id']);
            $this->assertNoConflict($attrs, null);
            $appointment = Appointment::create([...$attrs, 'status' => 'scheduled', 'created_by' => $actorId]);
            $this->audit->record('appointment.created', $actorId, 'appointment', $appointment->id, [
                'unit_id' => $attrs['unit_id'], 'patient_id' => $attrs['patient_id'], 'walk_in' => $attrs['is_walk_in'],
            ]);

            return $appointment;
        });
    }

    /**
     * Reagenda/edita. Só antes do atendimento começar.
     *
     * @param  array{unit_id: string, patient_id: string, appointment_type_id: string, professional_membership_id: ?string, starts_at: Carbon, ends_at: Carbon, is_walk_in: bool, notes: ?string}  $attrs
     */
    public function update(Appointment $appointment, array $attrs, string $actorId): void
    {
        if (! in_array($appointment->status, ['scheduled', 'confirmed', 'waiting'], true)) {
            throw ValidationException::withMessages(['starts_at' => 'Este agendamento não pode mais ser alterado ('.Appointment::STATUS[$appointment->status].').']);
        }
        DB::transaction(function () use ($appointment, $attrs, $actorId) {
            $this->lockProfessional($attrs['professional_membership_id']);
            $this->assertNoConflict($attrs, $appointment->id);
            $before = ['starts_at' => $appointment->starts_at->toIso8601String(), 'professional' => $appointment->professional_membership_id];
            $appointment->fill($attrs)->save();
            $this->audit->record('appointment.updated', $actorId, 'appointment', $appointment->id, [
                'before' => $before,
                'after' => ['starts_at' => $appointment->starts_at->toIso8601String(), 'professional' => $appointment->professional_membership_id],
            ]);
        });
    }

    public function transition(Appointment $appointment, string $to, string $actorId, ?string $reason = null): void
    {
        $from = $appointment->status;
        if (! in_array($to, Appointment::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Mudança de “'.Appointment::STATUS[$from].'” para “'.(Appointment::STATUS[$to] ?? $to).'” não é permitida.']);
        }
        if (in_array($to, Appointment::ATTEND, true) && ! $this->context->can('schedule:attend')) {
            abort(403);
        }
        if ($to === 'scheduled') {
            // Reabrir um cancelado/falta volta a ocupar o horário: precisa estar livre (ou ser encaixe).
            $this->assertNoConflict([
                'professional_membership_id' => $appointment->professional_membership_id,
                'starts_at' => $appointment->starts_at, 'ends_at' => $appointment->ends_at, 'is_walk_in' => $appointment->is_walk_in,
            ], $appointment->id);
        }

        $now = now();
        $appointment->status = $to;
        match ($to) {
            'confirmed' => $appointment->confirmed_at = $now,
            'waiting' => $appointment->checked_in_at = $now,
            'in_progress' => $appointment->started_at = $now,
            'completed' => $appointment->completed_at = $now,
            'cancelled' => [$appointment->cancelled_at, $appointment->cancel_reason] = [$now, $reason !== null && $reason !== '' ? mb_substr($reason, 0, 200) : null],
            'scheduled' => [$appointment->cancelled_at, $appointment->cancel_reason] = [null, null],
            default => null,
        };
        $appointment->save();
        $this->audit->record('appointment.status_changed', $actorId, 'appointment', $appointment->id, ['from' => $from, 'to' => $to]);
    }

    /**
     * Agendamentos do profissional que se sobrepõem ao intervalo (cancelados e faltas não ocupam a agenda).
     *
     * @return Collection<int, Appointment>
     */
    public function conflicts(?string $professionalId, Carbon $start, Carbon $end, ?string $exceptId): Collection
    {
        if ($professionalId === null) {
            return new Collection;
        }

        return Appointment::query()
            ->where('professional_membership_id', $professionalId)
            ->whereIn('status', Appointment::ACTIVE)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->with('patient:id,name')->orderBy('starts_at')->limit(5)->get();
    }

    /** Serializa agendamentos do mesmo profissional (evita dois encaixes simultâneos passarem na checagem). */
    private function lockProfessional(?string $membershipId): void
    {
        if ($membershipId !== null) {
            Membership::query()->whereKey($membershipId)->lockForUpdate()->first();
        }
    }

    /** @param  array<string, mixed>  $attrs */
    private function assertNoConflict(array $attrs, ?string $exceptId): void
    {
        if ($attrs['is_walk_in']) {
            return; // encaixe: sobreposição consciente, registrada no próprio agendamento
        }
        $clash = $this->conflicts($attrs['professional_membership_id'], $attrs['starts_at'], $attrs['ends_at'], $exceptId)->first();
        if ($clash) {
            $tz = Unit::query()->whereKey($clash->unit_id)->value('timezone') ?? 'America/Sao_Paulo';
            throw ValidationException::withMessages(['starts_at' => sprintf(
                'O profissional já tem %s das %s às %s. Escolha outro horário ou marque como encaixe.',
                $clash->patient->name,
                $clash->starts_at->timezone($tz)->format('H:i'),
                $clash->ends_at->timezone($tz)->format('H:i'),
            )]);
        }
    }
}
