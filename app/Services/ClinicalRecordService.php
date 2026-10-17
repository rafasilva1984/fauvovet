<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\EncounterAddendum;
use App\Models\EncounterDiagnosis;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientWeight;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prontuário da clínica ativa. Regras:
 *  - rascunho é editável por quem tem clinical:write; FINALIZAR exige clinical:sign (veterinário) e trava o registro;
 *  - depois de finalizado, só adendos (somente inclusão), assinados por quem escreveu;
 *  - auditoria registra criação, alterações (nomes dos campos, nunca o conteúdo), finalização, adendos e ACESSO.
 */
final class ClinicalRecordService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Audit $audit,
        private readonly ScheduleService $schedule,
    ) {}

    /** Abre (ou reabre) o atendimento do paciente; um por agendamento. */
    public function start(Patient $patient, ?Appointment $appointment, User $actor): Encounter
    {
        if ($appointment && $appointment->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['appointment' => 'O agendamento é de outro paciente.']);
        }
        if ($appointment && ($existing = Encounter::query()->where('appointment_id', $appointment->id)->first())) {
            return $existing;
        }
        $unitId = $appointment !== null ? $appointment->unit_id : ($this->context->unitId() ?? $this->schedule->allowedUnits()->first()?->id);
        if ($unitId === null) {
            throw ValidationException::withMessages(['unit' => 'Nenhuma unidade disponível para o seu acesso.']);
        }
        $encounter = Encounter::create([
            'unit_id' => $unitId,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'appointment_type_id' => $appointment?->appointment_type_id,
            'status' => 'draft',
            'started_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->audit->record('encounter.created', $actor->id, 'encounter', $encounter->id, ['patient_id' => $patient->id, 'appointment_id' => $appointment?->id]);

        return $encounter;
    }

    /**
     * Salva o rascunho (campos clínicos + lista de hipóteses/diagnósticos).
     *
     * @param  array<string, mixed>  $content
     * @param  list<array{description: string, code: ?string, status: string}>  $diagnoses
     */
    public function saveDraft(Encounter $encounter, array $content, array $diagnoses, User $actor): void
    {
        $this->assertDraft($encounter);
        DB::transaction(function () use ($encounter, $content, $diagnoses, $actor) {
            $encounter->fill(array_intersect_key($content, array_flip(Encounter::CONTENT)));
            $encounter->updated_by = $actor->id;
            $changed = array_values(array_diff(array_keys($encounter->getDirty()), ['updated_by']));

            $before = $encounter->diagnoses()->get(['description', 'code', 'status'])->map(fn ($d) => [$d->description, $d->code, $d->status])->all();
            $after = array_map(fn ($d) => [$d['description'], $d['code'], $d['status']], $diagnoses);
            if ($before !== $after) {
                // Rascunho: a lista é substituída por inteiro (ainda não é registro assinado).
                EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->get()->each->delete();
                foreach ($diagnoses as $i => $d) {
                    EncounterDiagnosis::create([...$d, 'encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id, 'position' => $i, 'created_by' => $actor->id]);
                }
                $changed[] = 'diagnoses';
            }
            $encounter->save();
            if ($changed !== []) {
                $this->audit->record('encounter.updated', $actor->id, 'encounter', $encounter->id, ['fields' => $changed]);
            }
        });
    }

    /** Finaliza e assina: trava o registro, grava o peso no histórico e conclui o agendamento em andamento. */
    public function finalize(Encounter $encounter, User $actor): void
    {
        $this->assertDraft($encounter);
        if (! $this->context->can('clinical:sign')) {
            abort(403);
        }
        $hasContent = collect(array_keys(Encounter::TEXT_FIELDS))->contains(fn ($f) => trim((string) $encounter->{$f}) !== '');
        if (! $hasContent) {
            throw ValidationException::withMessages(['chief_complaint' => 'Preencha ao menos a queixa principal ou outro campo clínico antes de finalizar.']);
        }

        DB::transaction(function () use ($encounter, $actor) {
            /** @var Encounter $locked */
            $locked = Encounter::query()->whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);
            $locked->status = 'finalized';
            $locked->finalized_at = now();
            $locked->finalized_by = $actor->id;
            $locked->signer_membership_id = $this->context->membership()?->id;
            $locked->updated_by = $actor->id;
            $locked->save();

            if ($locked->weight_kg !== null) {
                PatientWeight::create(['patient_id' => $locked->patient_id, 'encounter_id' => $locked->id, 'weight_kg' => $locked->weight_kg,
                    'measured_at' => $locked->started_at, 'created_by' => $actor->id]);
            }
            $this->audit->record('encounter.finalized', $actor->id, 'encounter', $locked->id, ['diagnoses' => $locked->diagnoses()->count()]);

            $appointment = $locked->appointment_id ? Appointment::query()->find($locked->appointment_id) : null;
            if ($appointment && $appointment->status === 'in_progress') {
                $this->schedule->transition($appointment, 'completed', $actor->id);
            }
            $encounter->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function addAddendum(Encounter $encounter, string $body, User $actor): EncounterAddendum
    {
        if ($encounter->isDraft()) {
            throw ValidationException::withMessages(['body' => 'Rascunho se edita direto; adendo é só para atendimento finalizado.']);
        }
        $addendum = EncounterAddendum::create([
            'encounter_id' => $encounter->id, 'body' => $body, 'author_id' => $actor->id,
            'author_membership_id' => (string) $this->context->membership()?->id,
        ]);
        $this->audit->record('encounter.addendum_added', $actor->id, 'encounter', $encounter->id, ['addendum_id' => $addendum->id]);

        return $addendum;
    }

    /** @param array{kind: string, severity: string, description: string} $data */
    public function addAlert(Patient $patient, array $data, User $actor): PatientAlert
    {
        $alert = PatientAlert::create([...$data, 'patient_id' => $patient->id, 'created_by' => $actor->id]);
        $this->audit->record('patient.alert_added', $actor->id, 'patient', $patient->id, ['alert_id' => $alert->id, 'kind' => $data['kind']]);

        return $alert;
    }

    public function resolveAlert(PatientAlert $alert, User $actor): void
    {
        if ($alert->resolved_at !== null) {
            return;
        }
        $alert->resolved_at = now();
        $alert->setAttribute('resolved_by', $actor->id);
        $alert->save();
        $this->audit->record('patient.alert_resolved', $actor->id, 'patient', $alert->patient_id, ['alert_id' => $alert->id]);
    }

    /** Auditoria de ACESSO ao prontuário (CLAUDE.md §8.1): quem abriu o quê e quando — sem conteúdo. */
    public function recordView(string $action, string $targetType, string $targetId, User $actor): void
    {
        $this->audit->record($action, $actor->id, $targetType, $targetId);
    }

    private function assertDraft(Encounter $encounter): void
    {
        if (! $encounter->isDraft()) {
            throw ValidationException::withMessages(['record' => 'Atendimento finalizado não pode ser alterado. Registre um adendo.']);
        }
    }
}
