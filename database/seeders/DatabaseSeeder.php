<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
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
            });
        }
        $this->command->info('Seed fictício: 2 clínicas, '.count(self::UNITS).' unidades, '.count(self::USERS).' usuários (senha = SEED_PASSWORD).');
    }
}
