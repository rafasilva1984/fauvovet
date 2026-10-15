<?php

namespace Tests\Feature\Isolation;

use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Unit;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\CrossTenantViolation;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextMissing;
use App\Tenancy\TenantOwned;
use App\Tenancy\TenantQueryFirewall;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Suíte obrigatória de isolamento entre clínicas (CLAUDE.md §5.3, §16.2 item 3), com dois tenants.
 * Sem RLS no MySQL, prova cada barreira separadamente: HTTP, escopo do model, firewall de SQL e FK composta.
 */
#[Group('tenant-isolation')]
final class TenantIsolationTest extends TestCase
{
    private function ctx(): TenantContext
    {
        return app(TenantContext::class);
    }

    // ---------------- HTTP ----------------

    public function test_clinica_a_lista_somente_suas_unidades(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/unidades')->assertOk()->assertSee('Aurora — Centro')->assertDontSee('Atendimento domiciliar');
    }

    public function test_id_previsivel_de_outra_clinica_retorna_404(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/unidades/'.Seed::UNITS[2]['id'])->assertNotFound();
        $this->get('/unidades/'.Seed::UNITS[0]['id'])->assertOk();
        $this->get('/unidades/nao-e-um-id')->assertNotFound();
    }

    public function test_tenant_id_manipulado_no_formulario_query_e_cabecalho_e_ignorado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->withHeader('X-Tenant-Id', Seed::TENANT_B)
            ->post('/unidades?tenant_id='.Seed::TENANT_B, ['name' => 'Tentativa cruzada', 'tenant_id' => Seed::TENANT_B])
            ->assertRedirect('/unidades');
        $owner = $this->ctx()->runAsSystem(fn () => DB::table('units')->where('name', 'Tentativa cruzada')->value('tenant_id'));
        $this->assertSame(Seed::TENANT_A, $owner);
    }

    public function test_equipe_e_auditoria_nao_incluem_outra_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->put('/configuracoes/aparencia', ['primary' => '#7c2d12', 'secondary' => '#1e40af', 'mode' => 'light']);
        $this->post('/sair');

        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/equipe')->assertOk()->assertSee($this->userEmail('ana'))->assertDontSee($this->userEmail('diana'));
        $this->get('/painel')->assertOk()->assertDontSee('Diana Titular');
    }

    public function test_usuaria_com_dois_vinculos_troca_de_clinica_explicitamente(): void
    {
        $this->loginInto('eva', Seed::TENANT_A);
        $this->get('/unidades')->assertSee('Aurora — Centro')->assertDontSee('Atendimento domiciliar');
        $this->post('/contexto', ['membership_id' => $this->membershipId('eva', Seed::TENANT_B)])->assertRedirect('/painel');
        $this->get('/unidades')->assertSee('Atendimento domiciliar')->assertDontSee('Aurora — Centro');
    }

    public function test_requisicoes_seguidas_de_clinicas_diferentes_nao_vazam_contexto(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/unidades')->assertSee('Aurora — Centro');
        $this->assertNull($this->ctx()->id(), 'contexto deve ser limpo ao fim da requisição');
        $this->post('/sair');
        $this->login('diana');
        // Nem a lista nem o cabeçalho/menu (dados de layout) podem trazer nada da clínica anterior.
        $this->get('/unidades')->assertSee('Atendimento domiciliar')->assertSee('Vet Horizonte Domiciliar (fictício)')->assertDontSee('Aurora');
    }

    // ---------------- Barreira 1: escopo do model (falha fechada) ----------------

    public function test_sem_clinica_ativa_consulta_de_model_e_recusada(): void
    {
        $this->expectException(TenantContextMissing::class);
        Unit::query()->get();
    }

    public function test_com_clinica_a_registro_de_b_e_invisivel(): void
    {
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            $this->assertNull(Unit::query()->find(Seed::UNITS[2]['id']));
            $this->assertSame(2, Unit::query()->count());
            $this->assertNull(Membership::query()->where('user_id', Seed::USERS['diana']['id'])->first());
        });
    }

    public function test_criar_registro_em_outra_clinica_e_recusado(): void
    {
        $this->expectException(CrossTenantViolation::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, fn () => Unit::query()->forceCreate(['tenant_id' => Seed::TENANT_B, 'name' => 'Invasão']));
    }

    public function test_tenant_id_nao_pode_ser_alterado(): void
    {
        $this->expectException(CrossTenantViolation::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            $unit = Unit::query()->findOrFail(Seed::UNITS[0]['id']);
            $unit->forceFill(['tenant_id' => Seed::TENANT_B])->save();
        });
    }

    public function test_auditoria_e_somente_inclusao(): void
    {
        $this->expectException(CrossTenantViolation::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            $event = AuditEvent::query()->create(['action' => 'test.event']);
            $event->delete();
        });
    }

    // ---------------- Barreira 2: firewall de SQL ----------------

    public function test_firewall_bloqueia_query_builder_sem_tenant_id(): void
    {
        $this->expectException(CrossTenantViolation::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, fn () => DB::table('units')->get());
    }

    public function test_firewall_bloqueia_sql_bruto_e_remocao_de_escopo(): void
    {
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            foreach ([
                fn () => DB::select('select * from `units`'),
                fn () => Unit::query()->withoutGlobalScopes()->get(),
                fn () => DB::table('memberships')->update(['status' => 'disabled']),
                fn () => DB::table('audit_events')->delete(),
            ] as $i => $attempt) {
                try {
                    $attempt();
                    $this->fail("tentativa {$i} não foi bloqueada");
                } catch (CrossTenantViolation) {
                    $this->addToAssertionCount(1);
                }
            }
        });
    }

    public function test_firewall_bloqueia_filtro_com_tenant_id_de_outra_clinica(): void
    {
        $this->expectException(CrossTenantViolation::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, fn () => DB::table('units')->where('tenant_id', Seed::TENANT_B)->get());
    }

    public function test_firewall_sem_clinica_ativa_bloqueia_tabela_de_clinica(): void
    {
        $this->expectException(TenantContextMissing::class);
        DB::table('units')->where('tenant_id', Seed::TENANT_A)->get();
    }

    public function test_todas_as_tabelas_com_tenant_id_estao_registradas_no_firewall(): void
    {
        $db = config('database.connections.mysql.database');
        $tables = collect(DB::select('select table_name as t from information_schema.columns where table_schema = ? and column_name = ?', [$db, 'tenant_id']))
            ->pluck('t')->map(fn ($t) => strtolower($t))->sort()->values()->all();
        $registered = TenantQueryFirewall::TENANT_TABLES;
        sort($registered);
        $this->assertSame($registered, $tables, 'Nova tabela com tenant_id precisa entrar em TenantQueryFirewall::TENANT_TABLES.');
    }

    public function test_todo_model_com_belongs_to_tenant_implementa_tenant_owned(): void
    {
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            $traits = class_uses_recursive($class);
            if (in_array(BelongsToTenant::class, $traits, true)) {
                $this->assertTrue(is_subclass_of($class, TenantOwned::class), "{$class} precisa implementar TenantOwned");
            }
        }
    }

    // ---------------- Barreira 3: FK composta no banco ----------------

    public function test_fk_composta_impede_ligar_vinculo_de_a_a_unidade_de_b(): void
    {
        $this->expectException(QueryException::class);
        $this->ctx()->runAsSystem(fn () => DB::table('membership_units')->insert([
            'tenant_id' => Seed::TENANT_A,
            'membership_id' => $this->membershipId('ana', Seed::TENANT_A),
            'unit_id' => Seed::UNITS[2]['id'], // unidade da clínica B
        ]));
    }
}
