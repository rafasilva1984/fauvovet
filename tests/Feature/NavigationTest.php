<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Tests\TestCase;

/** Navegação: menu por papel, página Configurações, abas da ficha e pendências do painel. */
final class NavigationTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const APPT_THOR_TODAY = '01K7FV0000000000000APA0001';

    public function test_titular_ve_configuracoes_agrupadas_e_menu_enxuto(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $menu = $this->get('/painel')->assertOk()->assertSee('Configurações');
        // Telas de configuração saíram do menu lateral: ficam na página Configurações.
        $menu->assertDontSee('Catálogo de exames')->assertDontSee('Sinônimos da busca')->assertDontSee('Tipos de atendimento');
        $this->get('/configuracoes')->assertOk()
            ->assertSeeInOrder(['Agenda', 'Tipos de atendimento', 'Catálogos clínicos', 'Catálogo de exames', 'Documentos', 'Modelos de documento', 'Papel timbrado e receituário', 'Clínica', 'Unidades', 'Aparência']);
    }

    public function test_tela_de_configuracao_tem_link_de_volta(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/configuracoes/exames')->assertOk()->assertSee('href="'.route('settings.index').'"', false);
        $this->get('/pacientes')->assertOk()->assertDontSee('href="'.route('settings.index').'" class="mb-4', false);
    }

    public function test_recepcao_nao_tem_configuracoes(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/painel')->assertOk()->assertDontSee('Configurações')->assertDontSee('Busca clínica');
        $this->get('/configuracoes')->assertForbidden();
    }

    public function test_ficha_do_paciente_tem_abas_por_papel(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR)->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSeeInOrder(['id="tab-resumo"', 'id="tab-prontuario"', 'id="tab-vacinas"', 'id="tab-exames"', 'id="tab-documentos"', 'id="tab-anexos"'], false);
        $this->post('/sair');
        // Recepção: sem prontuário, exames, documentos e anexos.
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertSee('id="tab-resumo"', false)->assertSee('id="tab-vacinas"', false)
            ->assertDontSee('id="tab-prontuario"', false)->assertDontSee('id="tab-exames"', false);
    }

    public function test_quem_aguarda_continua_no_painel_depois_do_horario(): void
    {
        // 23:00 no fuso da unidade: o agendamento das 09:00 já terminou, mas o paciente ainda está aguardando.
        $this->travelTo(now('America/Sao_Paulo')->setTime(23, 0));
        app(TenantContext::class)->runAsSystem(fn () => Appointment::query()->whereKey(self::APPT_THOR_TODAY)->update(['status' => 'waiting']));
        $this->loginInto('ana', Seed::TENANT_A);
        $html = (string) $this->get('/painel')->assertOk()->getContent();
        $agenda = substr($html, (int) strpos($html, 'Hoje na agenda'), (int) strpos($html, 'aria-label="Pendências"') - (int) strpos($html, 'Hoje na agenda'));
        $this->assertStringContainsString('Thor', $agenda);
        $this->assertStringContainsString('Aguardando', $agenda);
        $this->assertStringNotContainsString('Nenhum atendimento pendente hoje', $agenda);
    }

    public function test_atividade_recente_em_portugues_sem_codigos(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR); // leitura auditada: não entra no feed
        $this->get('/painel')->assertOk()->assertDontSee('patient.clinical_viewed')->assertDontSee('auth.context_selected')->assertDontSee('entrou no ambiente');
    }
}
