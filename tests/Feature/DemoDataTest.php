<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Patient;
use App\Services\DemoDataService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Dados de demonstração: chave de ligar, só titular, carga que alimenta busca/vacinas/casos, remoção limpa e isolamento. */
final class DemoDataTest extends TestCase
{
    private const URL = '/configuracoes/dados-de-demonstracao';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // anexos demo não vão para o disco real
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function demoRows(string $table, string $tenant): int
    {
        return $this->sys(fn () => DB::table($table)->where('tenant_id', $tenant)->where('id', 'like', DemoDataService::PREFIX.'%')->count());
    }

    public function test_desligado_por_padrao_responde_404(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get(self::URL)->assertNotFound();
        $this->post(self::URL)->assertNotFound();
        $this->get('/painel')->assertDontSee('Dados de demonstração');
    }

    public function test_titular_carrega_e_dados_aparecem_nas_telas(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/painel')->assertSee('Dados de demonstração');
        $this->get(self::URL)->assertOk()->assertSee('Carregar demonstração');
        $this->post(self::URL)->assertRedirect(self::URL)->assertSessionHasNoErrors();
        $this->assertSame(22, $this->demoRows('patients', Seed::TENANT_A));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'demo_data.loaded')->exists()));
        $this->get(self::URL)->assertSee('Demonstração carregada: 22 pacientes');
        $this->post(self::URL)->assertSessionHasErrors('demo'); // não duplica

        // Busca avançada: dermatite atópica confirmada em cães traz Bento/Luna/Zeus/Thor; descartado (Pipoca) fica fora.
        $res = $this->get('/pacientes?diagnostico=dermatite+atopica&situacao=confirmed&especie=canine')->assertOk();
        foreach ([1, 2, 4] as $n) {
            $res->assertSee('/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', $n), false);
        }
        $res->assertDontSee('/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', 3), false);
        $this->get('/pacientes?ano='.(now('America/Sao_Paulo')->year - 2))->assertSee('/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', 19), false); // jabuti, ~900 dias atrás

        // Busca clínica em texto livre já indexada para os pacientes demo (exemplos do guia).
        $pt = fn (int $n) => '/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', $n);
        $this->get('/busca?q=%22recusa+alimentar%22+OU+%22n%C3%A3o+se+alimenta%22')->assertSee($pt(11), false)->assertSee($pt(13), false)->assertDontSee($pt(1), false);
        $this->get('/busca?q=uveite&especie=bird')->assertSee($pt(18), false)->assertSee('Adendo');
        $this->get('/busca?q=penicilina+antibiotico')->assertSee($pt(1), false)->assertSee('Alerta — Alergia');
        $this->get('/busca?q=xampu')->assertSee('Adendo')->assertSee($pt(1), false);
        // Demais passos do guia 2026-10-19-busca-clinica.html (seção 5).
        $this->get('/busca?q=DOENCAS+RENAIS')->assertSee($pt(11), false)->assertSee($pt(12), false)->assertSee($pt(13), false);
        $this->get('/busca?q=renal+N%C3%83O+hipertens%C3%A3o')->assertSee($pt(11), false)->assertDontSee($pt(13), false)->assertSee('sem “hipertensao”');
        $this->get('/busca?q=dermatite&situacao=ruled_out')->assertSee($pt(3), false)->assertDontSee($pt(1), false);
        $this->get('/busca?q=uveite&especie=canine')->assertSee('Nada encontrado');
        $this->get('/pacientes?q=xampu')->assertSee('No prontuário: 1 paciente')->assertSee('<mark>xampu</mark>', false)->assertSee('Nenhum cadastro de paciente');
        $this->get('/vacinas')->assertOk()->assertSee('Bento')->assertSee('Atrasada');
        $this->get('/agenda')->assertOk()->assertSee('Zeus');
        $this->get('/casos')->assertOk()->assertSee('Dermatite atópica em cães')->assertSee('Doença renal crônica em gatos idosos')->assertSee('hipovitaminose A');

        // Exportação anonimizada: só consentidos; nome e telefone das observações omitidos.
        $csv = (string) $this->get('/casos/'.DemoDataService::idFor(Seed::TENANT_A, 'CC', 1).'/exportar.csv')->assertOk()->getContent();
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1); // cabeçalho + 2 consentidos
        $this->assertStringContainsString('[nome omitido] respondeu bem', $csv);
        foreach (['Bento', 'Beatriz', '90000-0101'] as $secret) {
            $this->assertStringNotContainsString($secret, $csv);
        }
    }

    /**
     * Regressão do relato de 19/10: na produção, a demonstração tinha sido carregada ANTES da versão com os exemplos
     * da busca — "xampu" e "recusa alimentar" não existiam nela, e a tela não avisava. Também: aspas curvas e "ou".
     */
    public function test_demo_antiga_avisa_e_atualiza_e_exemplos_do_guia_funcionam(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors();
        $gr1 = DemoDataService::idFor(Seed::TENANT_A, 'GR', 1);
        $pt = fn (int $n) => '/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', $n);
        // Simula a demonstração da versão 1 (marcador antigo, sem os textos novos).
        $this->sys(fn () => DB::table('guardians')->where('tenant_id', Seed::TENANT_A)->where('id', $gr1)->update(['notes' => 'Cadastro fictício de demonstração.']));
        $this->sys(fn () => DB::table('encounter_addenda')->where('tenant_id', Seed::TENANT_A)->where('id', 'like', DemoDataService::PREFIX.'%')->delete());
        $this->post('/busca/reindexar');
        $this->get('/busca?q=xampu')->assertSee('Nada encontrado')->assertSee('versão anterior');
        $this->get(self::URL)->assertSee('Demonstração desatualizada')->assertSee('Atualizar demonstração');

        $this->post(self::URL.'/atualizar')->assertRedirect(self::URL)->assertSessionHasNoErrors();
        $this->get(self::URL)->assertDontSee('Demonstração desatualizada');
        $this->assertSame(DemoDataService::VERSION, app(TenantContext::class)->runForTenant(Seed::TENANT_A, fn () => app(DemoDataService::class)->loadedVersion()));
        $this->get('/busca?q=xampu')->assertSee($pt(1), false)->assertDontSee('versão anterior');
        // Aspas curvas (celular/Word) e "ou" minúsculo, exatamente como digitado pelo proprietário.
        $this->get('/busca?q='.urlencode('“recusa alimentar” OU “não se alimenta”'))->assertSee($pt(11), false)->assertSee($pt(13), false);
        $this->get('/busca?q='.urlencode('“recusa alimentar” ou “não se alimenta”'))->assertSee($pt(11), false)->assertSee($pt(13), false);
        // Campo principal de Pacientes também procura no prontuário.
        $this->get('/pacientes?q=xampu')->assertSee('No prontuário: 1 paciente');
        // Diagnóstico por palavras, sem acento, plural e fora de ordem.
        $this->get('/pacientes?diagnostico=dermatites+atopicas')->assertSee($pt(1), false);
        $this->get('/pacientes?diagnostico=atopica+dermatite&especie=canine')->assertSee($pt(2), false);
        $this->get('/pacientes?diagnostico=L20')->assertSee($pt(1), false)->assertSee($pt(9), false)->assertDontSee($pt(3), false);
        $res = $this->get('/pacientes?diagnostico=dermatites+atopicas');
        foreach ([1, 2, 4, 9] as $n) {
            $res->assertSee($pt($n), false); // Bento, Luna, Zeus, Kiara
        }
        $res->assertDontSee($pt(3), false); // Pipoca: descartado
        $this->get('/pacientes?q=uve%C3%ADte')->assertSee('No prontuário: 1 paciente')->assertSee($pt(18), false); // busca do topo
        // Anexos demo: texto só existe dentro do PDF/DOCX; a foto fica "sem texto pesquisável".
        $this->get('/busca?q=tonometria')->assertSee($pt(18), false)->assertSee('Anexo PDF: Laudo oftalmológico');
        $this->get('/busca?q=encephalitozoon')->assertSee($pt(20), false)->assertSee('Anexo DOCX');
        $this->get('/busca?q=casco')->assertSee('Sem texto pesquisável');
        $this->assertSame(3, $this->sys(fn () => DB::table('attachments')->where('tenant_id', Seed::TENANT_A)->where('id', 'like', DemoDataService::PREFIX.'%')->count()));
        // Remover apaga também os arquivos dos anexos demo.
        $paths = $this->sys(fn () => DB::table('attachments')->where('tenant_id', Seed::TENANT_A)->where('id', 'like', DemoDataService::PREFIX.'%')->pluck('storage_path')->all());
        $this->post(self::URL.'/remover', ['confirm' => '1']);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_remover_apaga_demo_e_o_registrado_nela_sem_tocar_o_resto(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL);
        $bento = DemoDataService::idFor(Seed::TENANT_A, 'PT', 1);
        // A equipe registra coisas no paciente demo e vincula um paciente real a um tutor demo.
        $this->post("/pacientes/{$bento}/atendimentos")->assertRedirect();
        $this->post('/pacientes/'.Seed::PATIENTS[0]['id'].'/responsaveis', ['guardian_id' => DemoDataService::idFor(Seed::TENANT_A, 'GR', 2), 'relationship' => 'co_guardian']);

        $this->post(self::URL.'/remover')->assertSessionHasErrors('confirm');
        $this->post(self::URL.'/remover', ['confirm' => '1'])->assertRedirect(self::URL)->assertSessionHasNoErrors();
        foreach (['patients', 'guardians', 'clinical_cases', 'encounters', 'vaccinations', 'appointments'] as $table) {
            $this->assertSame(0, $this->demoRows($table, Seed::TENANT_A), $table);
        }
        $this->assertSame(0, $this->sys(fn () => DB::table('encounters')->where('tenant_id', Seed::TENANT_A)->where('patient_id', $bento)->count()));
        $this->assertSame(0, $this->sys(fn () => DB::table('clinical_search_documents')->where('tenant_id', Seed::TENANT_A)->where('patient_id', $bento)->count()));
        $this->assertSame(0, $this->sys(fn () => DB::table('guardian_patient')->where('tenant_id', Seed::TENANT_A)->where('guardian_id', DemoDataService::idFor(Seed::TENANT_A, 'GR', 2))->count()));
        // Dados que não são demo continuam.
        $this->assertSame(3, $this->sys(fn () => Patient::query()->where('tenant_id', Seed::TENANT_A)->count()));
        $this->assertSame(2, $this->sys(fn () => DB::table('encounters')->where('tenant_id', Seed::TENANT_A)->count()));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'demo_data.removed')->exists()));
        $this->get(self::URL)->assertSee('Carregar demonstração');
    }

    public function test_somente_titular(): void
    {
        config(['fauvovet.demo_data' => true]);
        foreach (['bruno', 'carla'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get(self::URL)->assertForbidden();
            $this->post(self::URL)->assertForbidden();
            $this->post(self::URL.'/remover', ['confirm' => '1'])->assertForbidden();
            $this->post('/sair');
        }
        $this->assertSame(0, $this->demoRows('patients', Seed::TENANT_A));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_demo_entre_clinicas(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors();
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get(self::URL)->assertOk()->assertSee('Nenhum dado de demonstração');
        $this->get('/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', 1))->assertNotFound();
        $this->get('/casos/'.DemoDataService::idFor(Seed::TENANT_A, 'CC', 1))->assertNotFound();
        $this->post(self::URL.'/remover', ['confirm' => '1'])->assertSessionHasNoErrors(); // remove só na B (nada)
        $this->assertSame(22, $this->demoRows('patients', Seed::TENANT_A));
    }
}
