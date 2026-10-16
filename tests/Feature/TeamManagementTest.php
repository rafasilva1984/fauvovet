<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Gestão de equipe: convites por link de uso único, papéis, unidades e desativação (Fase 1). */
final class TeamManagementTest extends TestCase
{
    private function ctx(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** Convida e devolve o token extraído do link mostrado uma única vez. */
    private function invite(string $email, array $roles): string
    {
        $res = $this->post('/equipe/convites', ['email' => $email, 'roles' => $roles]);
        $res->assertRedirect('/equipe')->assertSessionHasNoErrors();
        $link = (string) session('invite_link');
        $this->assertMatchesRegularExpression('#/convite/[A-Za-z0-9_-]{43}$#', $link);

        return substr($link, -43);
    }

    private function invitationByEmail(string $email): ?Invitation
    {
        return $this->ctx()->runAsSystem(fn () => Invitation::query()->where('email', $email)->latest('created_at')->first());
    }

    private function membershipOf(string $email, string $tenantId): ?Membership
    {
        return $this->ctx()->runAsSystem(fn () => Membership::query()->where('tenant_id', $tenantId)
            ->whereIn('user_id', User::query()->select('id')->where('email', $email))->first());
    }

    public function test_titular_convida_e_o_token_fica_salvo_somente_como_hash(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $token = $this->invite('Nova.Pessoa@Exemplo.test', ['receptionist']);

        $inv = $this->invitationByEmail('nova.pessoa@exemplo.test');
        $this->assertNotNull($inv);
        $this->assertSame(Seed::TENANT_A, $inv->tenant_id);
        $this->assertSame(hash('sha256', $token), $inv->token_hash);
        $this->assertSame(0, $this->ctx()->runAsSystem(fn () => DB::table('invitations')->where('token_hash', $token)->count()));
        $this->assertTrue($inv->expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'member.invited')->where('target_id', $inv->id)->exists()));

        $this->get('/equipe')->assertSee('nova.pessoa@exemplo.test')->assertSee('Convites pendentes');
    }

    public function test_pessoa_nova_aceita_cria_conta_e_entra_na_clinica(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $token = $this->invite('recep.nova@exemplo.test', ['receptionist']);
        $this->post('/sair');

        $this->get("/convite/{$token}")->assertOk()->assertSee('Clínica Veterinária Aurora (fictícia)')->assertSee('Criar conta e entrar na equipe');
        $this->post("/convite/{$token}", ['name' => 'Recepção Nova (fictícia)', 'password' => 'senha-forte-ficticia', 'password_confirmation' => 'senha-forte-ficticia'])
            ->assertRedirect('/painel');
        $this->assertAuthenticated();
        $this->get('/unidades')->assertOk()->assertSee('Aurora — Centro');

        $m = $this->membershipOf('recep.nova@exemplo.test', Seed::TENANT_A);
        $this->assertSame(['receptionist'], $m?->roleList());
        $this->assertSame('active', $m?->status);
        // Uso único: o link não funciona de novo.
        $this->post('/sair');
        $this->get("/convite/{$token}")->assertNotFound()->assertSee('Convite indisponível');
    }

    public function test_conta_existente_precisa_entrar_e_volta_ao_convite(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $token = $this->invite($this->userEmail('bruno'), ['veterinarian']);
        $this->post('/sair');

        $this->get("/convite/{$token}")->assertOk()->assertSee('Entrar para aceitar');
        // Sem sessão, não dá para "aceitar" uma conta existente sem a senha dela.
        $this->post("/convite/{$token}", ['name' => 'X', 'password' => 'outra-senha-qualquer', 'password_confirmation' => 'outra-senha-qualquer'])
            ->assertRedirect("/convite/{$token}");
        $this->assertNull($this->membershipOf($this->userEmail('bruno'), Seed::TENANT_B));

        $this->login('bruno')->assertRedirect(url("/convite/{$token}"));
        $this->get("/convite/{$token}")->assertOk()->assertSee('Aceitar convite');
        $this->post("/convite/{$token}")->assertRedirect('/painel');
        $this->get('/unidades')->assertSee('Atendimento domiciliar')->assertDontSee('Aurora — Centro');
        $this->assertSame(['veterinarian'], $this->membershipOf($this->userEmail('bruno'), Seed::TENANT_B)?->roleList());
    }

    public function test_convite_de_outro_email_e_recusado_para_quem_esta_logado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $token = $this->invite('alguem@exemplo.test', ['finance']);
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get("/convite/{$token}")->assertForbidden()->assertSee('outro e-mail');
        $this->post("/convite/{$token}")->assertForbidden();
        $this->assertNull($this->membershipOf($this->userEmail('diana'), Seed::TENANT_A));
    }

    public function test_token_invalido_expirado_ou_cancelado_responde_404_igual(): void
    {
        $this->get('/convite/'.str_repeat('a', 43))->assertNotFound()->assertSee('Convite indisponível');
        $this->get('/convite/curto')->assertNotFound();

        $this->loginInto('ana', Seed::TENANT_A);
        $expired = $this->invite('expira@exemplo.test', ['stock']);
        $this->ctx()->runAsSystem(fn () => DB::table('invitations')->where('email', 'expira@exemplo.test')->update(['expires_at' => now()->subMinute()]));
        $revoked = $this->invite('cancela@exemplo.test', ['stock']);
        $this->delete('/equipe/convites/'.$this->invitationByEmail('cancela@exemplo.test')?->id)->assertRedirect('/equipe');
        $this->post('/sair');

        foreach ([$expired, $revoked] as $token) {
            $this->get("/convite/{$token}")->assertNotFound()->assertSee('Convite indisponível');
            $this->post("/convite/{$token}", ['name' => 'X Y', 'password' => 'senha-forte-ficticia', 'password_confirmation' => 'senha-forte-ficticia'])->assertNotFound();
        }
        $this->assertSame(0, User::query()->whereIn('email', ['expira@exemplo.test', 'cancela@exemplo.test'])->count());
    }

    public function test_novo_convite_para_o_mesmo_email_invalida_o_link_anterior(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $first = $this->invite('repete@exemplo.test', ['stock']);
        $second = $this->invite('repete@exemplo.test', ['finance']);
        $this->post('/sair');
        $this->get("/convite/{$first}")->assertNotFound();
        $this->get("/convite/{$second}")->assertOk()->assertSee('Financeiro');
    }

    public function test_nao_convida_quem_ja_e_membro_ativo(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post('/equipe/convites', ['email' => $this->userEmail('bruno'), 'roles' => ['finance']])->assertSessionHasErrors('email');
    }

    public function test_gestao_nao_concede_papel_administrativo_e_titular_sim(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->put('/equipe/'.$this->membershipId('davi', Seed::TENANT_A), ['roles' => ['tenant_manager'], 'status' => 'active'])->assertSessionHasNoErrors();
        $this->post('/sair');

        $this->loginInto('davi', Seed::TENANT_A);
        $this->get('/equipe')->assertOk()->assertSee('Convidar pessoa')->assertDontSee('value="tenant_owner"', false);
        $this->post('/equipe/convites', ['email' => 'chefe@exemplo.test', 'roles' => ['tenant_owner']])->assertSessionHasErrors('roles');
        $this->post('/equipe/convites', ['email' => 'chefe@exemplo.test', 'roles' => ['tenant_manager']])->assertSessionHasErrors('roles');
        $this->post('/equipe/convites', ['email' => 'apoio@exemplo.test', 'roles' => ['clinical_assistant']])->assertSessionHasNoErrors();
        // Gestão não edita o titular nem se promove.
        $this->put('/equipe/'.$this->membershipId('ana', Seed::TENANT_A), ['roles' => ['receptionist'], 'status' => 'active'])->assertForbidden();
        $this->put('/equipe/'.$this->membershipId('bruno', Seed::TENANT_A), ['roles' => ['tenant_owner'], 'status' => 'active'])->assertSessionHasErrors('roles');
        $this->assertSame(['veterinarian'], $this->membershipOf($this->userEmail('bruno'), Seed::TENANT_A)?->roleList());
    }

    public function test_sem_permissao_nao_ve_formularios_nem_gerencia(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/equipe')->assertForbidden();
        $this->post('/equipe/convites', ['email' => 'x@exemplo.test', 'roles' => ['receptionist']])->assertForbidden();
        $this->get('/equipe/'.$this->membershipId('carla', Seed::TENANT_A))->assertForbidden();
        $this->put('/equipe/'.$this->membershipId('carla', Seed::TENANT_A), ['roles' => ['tenant_owner'], 'status' => 'active'])->assertForbidden();
    }

    public function test_titular_altera_papeis_e_unidades_e_audita(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $carla = $this->membershipId('carla', Seed::TENANT_A);
        $this->get("/equipe/{$carla}")->assertOk()->assertSee('Aurora — Zona Sul')->assertDontSee('Atendimento domiciliar');
        $this->put("/equipe/{$carla}", ['roles' => ['receptionist', 'finance'], 'units' => [Seed::UNITS[1]['id']], 'status' => 'active'])
            ->assertRedirect('/equipe')->assertSessionHasNoErrors();

        $m = $this->membershipOf($this->userEmail('carla'), Seed::TENANT_A);
        $this->assertSame(['receptionist', 'finance'], $m?->roleList());
        $units = $this->ctx()->runAsSystem(fn () => DB::table('membership_units')->where('membership_id', $carla)->pluck('unit_id')->all());
        $this->assertSame([Seed::UNITS[1]['id']], $units);
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'member.updated')->where('target_id', $carla)->exists()));

        // Restrição vale no login seguinte: só a unidade permitida aparece na escolha de contexto.
        $this->post('/sair');
        $this->login('carla');
        $this->get('/painel')->assertOk();
        $this->assertSame(Seed::UNITS[1]['id'], session('fv.unit_id'));
    }

    public function test_desativar_corta_o_acesso_na_requisicao_seguinte(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $brunoMembership = $this->membershipId('bruno', Seed::TENANT_A);
        $this->put("/equipe/{$brunoMembership}", ['roles' => ['veterinarian'], 'status' => 'disabled'])->assertSessionHasNoErrors();
        $this->post('/sair');

        // Simula a sessão que o Bruno já tinha aberta (clínica A escolhida antes da desativação).
        $bruno = User::query()->findOrFail(Seed::USERS['bruno']['id']);
        $this->actingAs($bruno)->withSession(['fv.membership_id' => $brunoMembership])
            ->get('/unidades')->assertRedirect('/contexto');
        $this->assertNull(session('fv.membership_id'));
        $this->assertSame('disabled', $this->membershipOf($this->userEmail('bruno'), Seed::TENANT_A)?->status);
    }

    public function test_ninguem_altera_o_proprio_acesso_e_a_clinica_nunca_fica_sem_titular(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->put('/equipe/'.$this->membershipId('ana', Seed::TENANT_A), ['roles' => ['veterinarian'], 'status' => 'active'])->assertSessionHasErrors('roles');
        $this->get('/equipe/'.$this->membershipId('ana', Seed::TENANT_A))->assertOk()->assertSee('Você não pode alterar o seu próprio acesso');

        // Promove Bruno a titular; Bruno tenta tirar a titularidade da Ana → permitido (ainda resta 1 titular).
        $this->put('/equipe/'.$this->membershipId('bruno', Seed::TENANT_A), ['roles' => ['tenant_owner'], 'status' => 'active'])->assertSessionHasNoErrors();
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->put('/equipe/'.$this->membershipId('ana', Seed::TENANT_A), ['roles' => ['veterinarian'], 'status' => 'active'])->assertSessionHasNoErrors();
        // Com Bruno como único titular, desativá-lo seria a única forma de zerar — e ele não pode editar a si mesmo.
        $owners = $this->ctx()->runForTenant(Seed::TENANT_A, fn () => Membership::query()->where('status', 'active')->get()
            ->filter(fn (Membership $m) => in_array('tenant_owner', $m->roleList(), true))->count());
        $this->assertSame(1, $owners);
    }

    public function test_papel_ou_unidade_inexistente_e_rejeitado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $carla = $this->membershipId('carla', Seed::TENANT_A);
        $this->put("/equipe/{$carla}", ['roles' => ['platform_admin'], 'status' => 'active'])->assertSessionHasErrors('roles.0');
        $this->put("/equipe/{$carla}", ['roles' => ['receptionist'], 'status' => 'qualquer'])->assertSessionHasErrors('status');
        $this->post('/equipe/convites', ['email' => 'x@exemplo.test', 'roles' => []])->assertSessionHasErrors('roles');
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_unidade_de_outra_clinica_nao_pode_ser_atribuida(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $carla = $this->membershipId('carla', Seed::TENANT_A);
        $this->put("/equipe/{$carla}", ['roles' => ['receptionist'], 'units' => [Seed::UNITS[2]['id']], 'status' => 'active'])
            ->assertSessionHasErrors('units.0');
        $this->assertSame(0, $this->ctx()->runAsSystem(fn () => DB::table('membership_units')->where('membership_id', $carla)->count()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_vinculo_e_convite_de_outra_clinica_respondem_404(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->invite('somente-b@exemplo.test', ['receptionist']);
        $invB = $this->invitationByEmail('somente-b@exemplo.test');
        $this->post('/sair');

        $this->loginInto('ana', Seed::TENANT_A);
        $dianaInB = $this->membershipId('diana', Seed::TENANT_B);
        $this->get("/equipe/{$dianaInB}")->assertNotFound();
        $this->put("/equipe/{$dianaInB}", ['roles' => ['receptionist'], 'status' => 'disabled'])->assertNotFound();
        $this->delete("/equipe/convites/{$invB?->id}")->assertNotFound();
        $this->get('/equipe')->assertDontSee('somente-b@exemplo.test');
        $this->assertSame('active', $this->membershipOf($this->userEmail('diana'), Seed::TENANT_B)?->status);
        $this->assertTrue($this->ctx()->runAsSystem(fn () => Invitation::query()->whereKey($invB?->id)->first()?->isPending()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_convite_da_clinica_b_so_cria_vinculo_na_b(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $token = $this->invite('so-na-b@exemplo.test', ['finance']);
        $this->post('/sair');
        $this->post("/convite/{$token}", ['name' => 'Pessoa B (fictícia)', 'password' => 'senha-forte-ficticia', 'password_confirmation' => 'senha-forte-ficticia'])
            ->assertRedirect('/painel');
        $this->assertNotNull($this->membershipOf('so-na-b@exemplo.test', Seed::TENANT_B));
        $this->assertNull($this->membershipOf('so-na-b@exemplo.test', Seed::TENANT_A));
        $this->get('/unidades')->assertSee('Atendimento domiciliar')->assertDontSee('Aurora');
    }
}
