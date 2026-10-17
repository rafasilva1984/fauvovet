<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Tests\TestCase;

/** Permissões verificadas no backend (negar por padrão), não só escondidas na interface. */
final class RbacTest extends TestCase
{
    public function test_recepcao_ve_unidades_mas_nao_equipe_nem_aparencia(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/unidades')->assertOk();
        $this->get('/equipe')->assertForbidden();
        $this->get('/configuracoes/aparencia')->assertForbidden();
        $this->put('/configuracoes/aparencia', ['primary' => '#0e5e66', 'secondary' => '#7a4fa3', 'mode' => 'light'])->assertForbidden();
    }

    public function test_veterinario_nao_cria_unidade(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/unidades', ['name' => 'Unidade X'])->assertForbidden();
    }

    public function test_menu_reflete_o_papel(): void
    {
        $this->loginInto('davi', Seed::TENANT_A);
        $this->get('/painel')->assertOk()->assertSee('Financeiro')->assertDontSee('Personalizar aparência');
    }

    public function test_titular_cria_unidade_com_uf_normalizada_e_nome_unico(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post('/unidades', ['name' => 'Aurora — Norte', 'city' => 'Cidade Fictícia', 'state' => 'SP'])->assertRedirect('/unidades');
        $this->get('/unidades')->assertSee('Aurora — Norte')->assertSee('SP');
        $this->post('/unidades', ['name' => 'AURORA — NORTE'])->assertSessionHasErrors(['name' => 'Já existe uma unidade com este nome.']);
    }

    public function test_titular_altera_tema_com_contraste_valido_e_gera_auditoria(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->put('/configuracoes/aparencia', ['primary' => '#1D4E89', 'secondary' => '#7a4fa3', 'mode' => 'dark'])->assertRedirect('/configuracoes/aparencia');
        $html = $this->get('/painel')->assertOk()->getContent();
        $this->assertStringContainsString('--fv-primary: #1d4e89', $html);
        $this->assertStringContainsString('data-theme="dark"', $html);
        $found = app(TenantContext::class)->runForTenant(Seed::TENANT_A, fn () => AuditEvent::query()->where('action', 'tenant.theme_updated')->exists());
        $this->assertTrue($found);
    }

    public function test_tema_sem_contraste_ou_com_injecao_e_recusado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->put('/configuracoes/aparencia', ['primary' => '#808080', 'secondary' => '#7a4fa3', 'mode' => 'light'])->assertSessionHasErrors('primary');
        $this->put('/configuracoes/aparencia', ['primary' => 'red;}body{', 'secondary' => '#7a4fa3', 'mode' => 'light'])->assertSessionHasErrors('primary');
    }
}
