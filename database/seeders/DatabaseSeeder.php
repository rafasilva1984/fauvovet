<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Encounter;
use App\Models\EncounterDiagnosis;
use App\Models\Guardian;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientWeight;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Models\User;
use App\Services\ScheduleService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dados 100% FICTÍCIOS para desenvolvimento e testes: dois tenants (A e B) e uma usuária com vínculo
 * em ambos. Domínios .test são reservados (RFC 2606). Idempotente (IDs fixos + updateOrCreate).
 */
class DatabaseSeeder extends Seeder
{
    public const TENANT_A = '01K7FV00000000000000000AAA';

    public const TENANT_B = '01K7FV00000000000000000BBB';

    public const UNITS = [
        ['id' => '01K7FV000000000000000TNA01', 'tenant' => self::TENANT_A, 'name' => 'Aurora — Centro'],
        ['id' => '01K7FV000000000000000TNA02', 'tenant' => self::TENANT_A, 'name' => 'Aurora — Zona Sul'],
        ['id' => '01K7FV000000000000000TNB01', 'tenant' => self::TENANT_B, 'name' => 'Atendimento domiciliar'],
    ];

    public const USERS = [
        'ana' => ['id' => '01K7FV00000000000000ESRA01', 'email' => 'ana.titular@aurora.exemplo.test', 'name' => 'Ana Titular (fictícia)', 'm' => [self::TENANT_A => ['tenant_owner', 'veterinarian']]],
        'bruno' => ['id' => '01K7FV00000000000000ESRA02', 'email' => 'bruno.vet@aurora.exemplo.test', 'name' => 'Bruno Veterinário (fictício)', 'm' => [self::TENANT_A => ['veterinarian']]],
        'carla' => ['id' => '01K7FV00000000000000ESRA03', 'email' => 'carla.recepcao@aurora.exemplo.test', 'name' => 'Carla Recepção (fictícia)', 'm' => [self::TENANT_A => ['receptionist']]],
        'davi' => ['id' => '01K7FV00000000000000ESRA04', 'email' => 'davi.financeiro@aurora.exemplo.test', 'name' => 'Davi Financeiro (fictício)', 'm' => [self::TENANT_A => ['finance']]],
        'diana' => ['id' => '01K7FV00000000000000ESRB01', 'email' => 'diana.titular@horizonte.exemplo.test', 'name' => 'Diana Titular (fictícia)', 'm' => [self::TENANT_B => ['tenant_owner', 'veterinarian']]],
        'eva' => ['id' => '01K7FV00000000000000ESRC01', 'email' => 'eva.plantonista@exemplo.test', 'name' => 'Eva Plantonista (fictícia)', 'm' => [self::TENANT_A => ['veterinarian'], self::TENANT_B => ['veterinarian']]],
    ];

    /** CRMV fictícios dos veterinários do seed (aparecem nas receitas de teste). */
    public const CRMV = ['ana' => 'CRMV-XX 00000', 'bruno' => 'CRMV-XX 00001', 'diana' => 'CRMV-XX 00002', 'eva' => 'CRMV-XX 00003'];

    /** Responsáveis fictícios (telefones e CPF de exemplo, sem relação com pessoas reais). */
    public const GUARDIANS = [
        ['id' => '01K7FV0000000000000GRDA001', 'tenant' => self::TENANT_A, 'name' => 'Marina Souza (fictícia)', 'phone' => '11987654321', 'email' => 'marina@aurora.exemplo.test', 'document_number' => '52998224725'],
        ['id' => '01K7FV0000000000000GRDA002', 'tenant' => self::TENANT_A, 'name' => 'Rafael Lima (fictício)', 'phone' => '11912345678', 'email' => null, 'document_number' => null],
        ['id' => '01K7FV0000000000000GRDB001', 'tenant' => self::TENANT_B, 'name' => 'Helena Prado (fictícia)', 'phone' => '31988887777', 'email' => 'helena@horizonte.exemplo.test', 'document_number' => null],
    ];

    /** Pacientes fictícios de espécies variadas; 'g' = [id do responsável => vínculo]. */
    public const PATIENTS = [
        ['id' => '01K7FV0000000000000PTA0001', 'tenant' => self::TENANT_A, 'name' => 'Thor', 'species' => 'canine', 'species_detail' => null, 'breed' => 'SRD', 'sex' => 'male', 'neutered' => 'yes', 'birth_date' => '2019-03-10', 'microchip' => '985112000000001', 'g' => ['01K7FV0000000000000GRDA001' => 'primary']],
        ['id' => '01K7FV0000000000000PTA0002', 'tenant' => self::TENANT_A, 'name' => 'Mia', 'species' => 'feline', 'species_detail' => null, 'breed' => 'Persa', 'sex' => 'female', 'neutered' => 'yes', 'birth_date' => '2021-08-01', 'microchip' => null, 'g' => ['01K7FV0000000000000GRDA001' => 'primary', '01K7FV0000000000000GRDA002' => 'co_guardian']],
        ['id' => '01K7FV0000000000000PTA0003', 'tenant' => self::TENANT_A, 'name' => 'Kiwi', 'species' => 'bird', 'species_detail' => 'Calopsita', 'breed' => null, 'sex' => 'unknown', 'neutered' => 'unknown', 'birth_date' => null, 'microchip' => null, 'g' => ['01K7FV0000000000000GRDA002' => 'primary']],
        ['id' => '01K7FV0000000000000PTB0001', 'tenant' => self::TENANT_B, 'name' => 'Jabu', 'species' => 'reptile', 'species_detail' => 'Jabuti-piranga', 'breed' => null, 'sex' => 'female', 'neutered' => 'no', 'birth_date' => '2010-01-01', 'microchip' => null, 'g' => ['01K7FV0000000000000GRDB001' => 'primary']],
        ['id' => '01K7FV0000000000000PTB0002', 'tenant' => self::TENANT_B, 'name' => 'Nina', 'species' => 'canine', 'species_detail' => null, 'breed' => 'Shih Tzu', 'sex' => 'female', 'neutered' => 'yes', 'birth_date' => '2018-05-20', 'microchip' => null, 'g' => ['01K7FV0000000000000GRDB001' => 'primary']],
    ];

    /** Agendamentos fictícios de HOJE (horário local da unidade): [id, paciente, unidade, usuário profissional, hora, status]. */
    public const APPOINTMENTS = [
        ['01K7FV0000000000000APA0001', '01K7FV0000000000000PTA0001', '01K7FV000000000000000TNA01', 'bruno', '09:00', 'scheduled'],
        ['01K7FV0000000000000APA0002', '01K7FV0000000000000PTA0002', '01K7FV000000000000000TNA01', 'ana', '10:00', 'confirmed'],
        ['01K7FV0000000000000APB0001', '01K7FV0000000000000PTB0001', '01K7FV000000000000000TNB01', 'diana', '14:00', 'scheduled'],
    ];

    /** Prontuário fictício: [id, tenant, paciente, unidade, autor, dias atrás, peso, queixa, diagnóstico, status do dx]. */
    public const ENCOUNTERS = [
        ['01K7FV0000000000000ENA0001', self::TENANT_A, '01K7FV0000000000000PTA0001', '01K7FV000000000000000TNA01', 'bruno', 120, '27.400', 'Coceira intensa nas patas e orelhas há 3 semanas.', 'Dermatite atópica', 'suspected'],
        ['01K7FV0000000000000ENA0002', self::TENANT_A, '01K7FV0000000000000PTA0001', '01K7FV000000000000000TNA01', 'bruno', 30, '28.150', 'Retorno: prurido reduziu com o tratamento.', 'Dermatite atópica', 'confirmed'],
        ['01K7FV0000000000000ENB0001', self::TENANT_B, '01K7FV0000000000000PTB0001', '01K7FV000000000000000TNB01', 'diana', 60, '4.200', 'Recusa alimentar há 5 dias.', 'Suspeita de doença renal', 'suspected'],
    ];

    public function run(TenantContext $context): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Seed fictício recusado em produção.');
        }
        $password = (string) config('fauvovet.seed_password');

        $tenants = [
            self::TENANT_A => ['trade_name' => 'Clínica Veterinária Aurora (fictícia)', 'person_type' => 'PJ', 'document_number' => '11222333000181', 'theme' => ['#0e5e66', '#7a4fa3']],
            self::TENANT_B => ['trade_name' => 'Vet Horizonte Domiciliar (fictício)', 'person_type' => 'PF', 'document_number' => null, 'theme' => ['#7c2d12', '#1e40af']],
        ];

        $users = [];
        foreach (self::USERS as $key => $u) {
            $user = User::query()->find($u['id']) ?? new User;
            $user->id = $u['id'];
            $user->fill(['name' => $u['name'], 'email' => $u['email'], 'password' => $password, 'crmv' => self::CRMV[$key] ?? null]);
            $user->failed_login_count = 0;
            $user->locked_until = null;
            $user->save();
            $users[$key] = $user;
        }

        foreach ($tenants as $tenantId => $t) {
            $context->runForTenant($tenantId, function () use ($tenantId, $t, $users) {
                $tenant = Tenant::query()->find($tenantId) ?? new Tenant;
                $tenant->id = $tenantId;
                $tenant->fill(['trade_name' => $t['trade_name'], 'person_type' => $t['person_type'], 'document_number' => $t['document_number']])->save();

                $theme = TenantTheme::query()->first() ?? new TenantTheme;
                $theme->fill(['primary_color' => $t['theme'][0], 'secondary_color' => $t['theme'][1], 'mode' => 'system'])->save();

                foreach (self::UNITS as $u) {
                    if ($u['tenant'] === $tenantId) {
                        $unit = Unit::query()->find($u['id']) ?? new Unit;
                        $unit->id = $u['id'];
                        $unit->fill(['name' => $u['name'], 'timezone' => 'America/Sao_Paulo'])->save();
                    }
                }
                foreach (self::USERS as $key => $u) {
                    if (isset($u['m'][$tenantId])) {
                        $m = Membership::query()->where('user_id', $users[$key]->id)->first() ?? new Membership(['user_id' => $users[$key]->id]);
                        $m->fill(['roles' => $u['m'][$tenantId], 'status' => 'active'])->save();
                    }
                }
                foreach (self::GUARDIANS as $g) {
                    if ($g['tenant'] === $tenantId) {
                        $guardian = Guardian::query()->find($g['id']) ?? new Guardian;
                        $guardian->id = $g['id'];
                        $guardian->fill(['name' => $g['name'], 'person_type' => 'PF', 'phone' => $g['phone'], 'email' => $g['email'], 'document_number' => $g['document_number'], 'status' => 'active'])->save();
                    }
                }
                app(ScheduleService::class)->ensureDefaultTypes();
                foreach (self::PATIENTS as $pt) {
                    if ($pt['tenant'] === $tenantId) {
                        $patient = Patient::query()->find($pt['id']) ?? new Patient;
                        $patient->id = $pt['id'];
                        $patient->fill(collect($pt)->only(['name', 'species', 'species_detail', 'breed', 'sex', 'neutered', 'birth_date', 'microchip'])->all() + ['status' => 'active'])->save();
                        foreach ($pt['g'] as $guardianId => $relationship) {
                            DB::table('guardian_patient')->insertOrIgnore(['tenant_id' => $tenantId, 'guardian_id' => $guardianId, 'patient_id' => $pt['id'], 'relationship' => $relationship, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }
            });
        }
        foreach ([self::TENANT_A, self::TENANT_B] as $tenantId) {
            $context->runForTenant($tenantId, function () use ($tenantId, $users) {
                $type = AppointmentType::query()->where('name', 'Consulta')->firstOrFail();
                foreach (self::APPOINTMENTS as [$id, $patientId, $unitId, $userKey, $time, $status]) {
                    if (array_column(self::UNITS, 'tenant', 'id')[$unitId] !== $tenantId) {
                        continue;
                    }
                    $start = ScheduleService::toUtc(now('America/Sao_Paulo')->format('Y-m-d'), $time, 'America/Sao_Paulo');
                    $appointment = Appointment::query()->find($id) ?? new Appointment;
                    $appointment->id = $id;
                    $appointment->fill([
                        'unit_id' => $unitId, 'patient_id' => $patientId, 'appointment_type_id' => $type->id,
                        'professional_membership_id' => Membership::query()->where('user_id', $users[$userKey]->id)->value('id'),
                        'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($type->duration_minutes),
                        'status' => $status, 'is_walk_in' => false,
                    ])->save();
                }
            });
        }
        foreach (self::ENCOUNTERS as [$id, $tenantId, $patientId, $unitId, $userKey, $daysAgo, $weight, $complaint, $dx, $dxStatus]) {
            $context->runForTenant($tenantId, function () use ($id, $patientId, $unitId, $userKey, $daysAgo, $weight, $complaint, $dx, $dxStatus, $users) {
                if (Encounter::query()->whereKey($id)->exists()) {
                    return; // finalizado é imutável: o seed não reescreve
                }
                $when = now()->subDays($daysAgo)->setTime(13, 0);
                $e = new Encounter([
                    'unit_id' => $unitId, 'patient_id' => $patientId, 'status' => 'draft', 'started_at' => $when,
                    'chief_complaint' => $complaint, 'physical_exam' => 'Exame físico fictício para demonstração.',
                    'plan' => 'Conduta fictícia para demonstração.', 'weight_kg' => $weight, 'temperature_c' => '38.6',
                    'created_by' => $users[$userKey]->id,
                ]);
                $e->id = $id;
                $e->save();
                EncounterDiagnosis::create(['encounter_id' => $id, 'patient_id' => $patientId, 'description' => $dx, 'status' => $dxStatus, 'position' => 0, 'created_by' => $users[$userKey]->id]);
                $e->status = 'finalized';
                $e->finalized_at = $when->copy()->addMinutes(40);
                $e->finalized_by = $users[$userKey]->id;
                $e->signer_membership_id = Membership::query()->where('user_id', $users[$userKey]->id)->value('id');
                $e->save();
                PatientWeight::create(['patient_id' => $patientId, 'encounter_id' => $id, 'weight_kg' => $weight, 'measured_at' => $when, 'created_by' => $users[$userKey]->id]);
            });
        }
        $context->runForTenant(self::TENANT_A, function () use ($users) {
            if (! PatientAlert::query()->where('patient_id', '01K7FV0000000000000PTA0001')->exists()) {
                PatientAlert::create(['patient_id' => '01K7FV0000000000000PTA0001', 'kind' => 'allergy', 'severity' => 'high', 'description' => 'Alergia a penicilina (fictícia)', 'created_by' => $users['bruno']->id]);
            }
        });
        $this->command->info('Seed fictício: 2 clínicas, '.count(self::UNITS).' unidades, '.count(self::USERS).' usuários, '.count(self::GUARDIANS).' responsáveis, '.count(self::PATIENTS).' pacientes, '.count(self::APPOINTMENTS).' agendamentos hoje (senha = SEED_PASSWORD).');
    }
}
