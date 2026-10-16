<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Models\User;
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
            $user->fill(['name' => $u['name'], 'email' => $u['email'], 'password' => $password]);
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
        $this->command->info('Seed fictício: 2 clínicas, '.count(self::UNITS).' unidades, '.count(self::USERS).' usuários, '.count(self::GUARDIANS).' responsáveis, '.count(self::PATIENTS).' pacientes (senha = SEED_PASSWORD).');
    }
}
