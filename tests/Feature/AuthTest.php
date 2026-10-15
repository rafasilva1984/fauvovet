<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_rota_protegida_sem_sessao_vai_para_o_login(): void
    {
        $this->get('/painel')->assertRedirect('/login');
        $this->get('/unidades')->assertRedirect('/login');
    }

    public function test_mensagem_identica_para_email_inexistente_e_senha_errada(): void
    {
        $a = $this->from('/login')->post('/login', ['email' => $this->userEmail('bruno'), 'password' => 'errada-123456']);
        $b = $this->from('/login')->post('/login', ['email' => 'ninguem@exemplo.test', 'password' => 'errada-123456']);
        $a->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $b->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->assertGuest();
    }

    public function test_login_com_uma_unidade_entra_direto_no_painel(): void
    {
        $this->login('diana')->assertRedirect('/painel');
        $this->get('/painel')->assertOk()->assertSee('Vet Horizonte Domiciliar (fictício)');
    }

    public function test_login_com_varias_unidades_exige_escolha(): void
    {
        $this->login('bruno')->assertRedirect('/contexto');
        $this->get('/painel')->assertRedirect('/contexto');
        $this->get('/contexto')->assertOk()->assertSee('Aurora — Zona Sul');
    }

    public function test_sessao_e_regenerada_no_login_e_na_troca_de_contexto(): void
    {
        $this->get('/login');
        $before = session()->getId();
        $this->login('bruno');
        $afterLogin = session()->getId();
        $this->post('/contexto', ['membership_id' => $this->membershipId('bruno', Seed::TENANT_A)]);
        $this->assertNotSame($before, $afterLogin);
        $this->assertNotSame($afterLogin, session()->getId());
    }

    public function test_nao_aceita_vinculo_de_outro_usuario_nem_unidade_de_outra_clinica(): void
    {
        $this->login('bruno');
        $this->post('/contexto', ['membership_id' => $this->membershipId('diana', Seed::TENANT_B)])->assertForbidden();
        $this->post('/contexto', ['membership_id' => $this->membershipId('bruno', Seed::TENANT_A), 'unit_id' => Seed::UNITS[2]['id']])->assertForbidden();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->login('diana');
        $this->get('/painel')->assertOk();
        $this->post('/sair')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/painel')->assertRedirect('/login');
    }

    public function test_bloqueio_apos_falhas_consecutivas(): void
    {
        config(['fauvovet.login.max_failures' => 3]);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $this->userEmail('carla'), 'password' => 'errada-errada']);
        }
        $this->from('/login')->login('carla')->assertSessionHasErrors(['email' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.']);
        $this->assertGuest();
    }

    public function test_limite_de_tentativas_de_login_por_email(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'alvo@exemplo.test', 'password' => 'errada-'.$i]);
        }
        $this->post('/login', ['email' => 'alvo@exemplo.test', 'password' => 'qualquer'])->assertStatus(429);
    }

    public function test_usuario_desativado_nao_entra(): void
    {
        DB::table('users')->where('id', Seed::USERS['carla']['id'])->update(['status' => 'disabled']);
        $this->login('carla')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_vinculo_desativado_perde_acesso_na_proxima_requisicao(): void
    {
        $this->login('diana');
        $this->get('/painel')->assertOk();
        app(TenantContext::class)->runAsSystem(fn () => DB::table('memberships')
            ->where('user_id', Seed::USERS['diana']['id'])->update(['status' => 'disabled']));
        $this->get('/painel')->assertRedirect('/contexto');
    }

    public function test_clinica_suspensa_perde_acesso(): void
    {
        $this->login('diana');
        app(TenantContext::class)->runAsSystem(fn () => DB::table('tenants')->where('id', Seed::TENANT_B)->update(['status' => 'suspended']));
        $this->get('/painel')->assertRedirect('/contexto');
    }

    public function test_senha_guardada_com_argon2id_e_nunca_em_texto(): void
    {
        $hash = User::query()->whereKey(Seed::USERS['ana']['id'])->value('password');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    /** O cabeçalho Set-Cookie real é verificado no E2E/smoke (servidor); aqui, a configuração que o gera. */
    public function test_configuracao_do_cookie_de_sessao_e_segura(): void
    {
        $this->assertTrue(filter_var(config('session.http_only'), FILTER_VALIDATE_BOOL));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue(filter_var(config('session.encrypt'), FILTER_VALIDATE_BOOL));
        $this->assertSame('/fauvovet', config('session.path'));
        $this->assertFalse(filter_var(config('session.expire_on_close'), FILTER_VALIDATE_BOOL));
        $this->assertLessThanOrEqual(480, (int) config('session.lifetime'));
    }
}
