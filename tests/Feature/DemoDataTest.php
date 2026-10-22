<?php

namespace Tests\Feature;

use App\Documents\PdfRenderer;
use App\Models\AuditEvent;
use App\Models\Patient;
use App\Services\DemoDataService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Http\UploadedFile;
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
        $this->get('/configuracoes')->assertOk()->assertSee('Dados de demonstração');
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

    /** Demonstração v4 (fecha Fase 2 + início da Fase 3): passos do guia 2026-10-20-fase2-fase3.html, seção 5. */
    public function test_demo_v4_sinonimos_pesquisa_salva_modelo_importado_e_exames(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, '10 exames'));
        $pt = fn (int $n) => '/pacientes/'.DemoDataService::idFor(Seed::TENANT_A, 'PT', $n);
        $id = fn (string $k, int $n) => DemoDataService::idFor(Seed::TENANT_A, $k, $n);

        // Sinônimos: "coceira" também acha "prurido"; "anorexia" acha recusa alimentar / não se alimenta.
        $this->get('/configuracoes/sinonimos')->assertSee('recusa alimentar = não se alimenta = anorexia');
        $this->get('/busca?q=coceira')->assertSee('Sinônimos da clínica usados')->assertSee($pt(1), false)->assertSee('<mark>prurido</mark>', false);
        $this->get('/busca?q=anorexia')->assertSee($pt(11), false)->assertSee($pt(13), false);
        $this->get('/busca?q=DRC')->assertSee($pt(12), false);
        // Pesquisa salva compartilhada.
        $this->get('/busca')->assertSee('Gatos com doença renal (demonstração)');
        $this->get('/busca?q=renal&especie=feline')->assertSee($pt(11), false)->assertDontSee($pt(1), false);
        // Modelo importado de DOCX: desativado, com variáveis e original.
        $this->get('/configuracoes/modelos-de-documento')->assertSee('Termo de sedação (importado — demonstração)')->assertSee('importado de termo-sedacao-clinica.docx');
        $this->get('/configuracoes/modelos-de-documento/'.$id('TP', 1).'/editar')->assertSee('Paciente: {{paciente.nome}}', false)->assertSee('Assinatura: [completar]');
        $this->get('/configuracoes/modelos-de-documento/'.$id('TP', 1).'/original')->assertOk();
        // Exames: uma situação de cada.
        $this->get('/exames')->assertSee('Bento · Citologia')->assertSee('Toby · Ecocardiograma')->assertDontSee('Simba · Bioquímica renal');
        $this->get('/exames?situacao=reviewed')->assertSee('Simba · Bioquímica renal');
        $this->get('/exames?situacao=cancelled')->assertSee('Pipoca · Urinálise');
        $this->get('/exames/'.$id('EX', 2))->assertSee('Revisar e assinar')->assertSee('Malassezia');
        $this->get('/busca?q=giardia')->assertSee($pt(12), false)->assertSee('Exame: Coproparasitológico');

        // Passo 3: revisar o exame do Bento; passo 7: sinônimo criado pelo usuário.
        $this->post('/exames/'.$id('EX', 2).'/revisar', ['interpretation' => 'Piodermite com Malassezia (fictício).', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->get('/exames/'.$id('EX', 2))->assertSee('Revisado e assinado por Ana');
        $this->get('/exames')->assertSeeInOrder(['Em aberto', '4']);
        $this->post('/configuracoes/sinonimos', ['terms' => "vômito\nêmese"])->assertSessionHasNoErrors();
        $this->get('/busca?q=emese')->assertSee($pt(10), false)->assertSee('<mark>Vômito</mark>', false);

        $tpl = $this->sys(fn () => DB::table('document_templates')->where('id', $id('TP', 1))->value('source_path'));
        $this->post(self::URL.'/remover', ['confirm' => '1'])->assertSessionHasNoErrors();
        foreach (['exam_requests', 'clinical_search_synonyms', 'saved_searches', 'document_templates'] as $table) {
            $this->assertSame(0, $this->demoRows($table, Seed::TENANT_A), $table);
        }
        Storage::disk('local')->assertMissing((string) $tpl);
        $this->get('/busca?q=coceira')->assertDontSee('Sinônimos da clínica usados');
    }

    /** Demonstração v7: catálogo de medicamentos e receitas em uso (guia 2026-10-21-prescricao-estruturada.html, seção 5). */
    public function test_demo_v7_medicamentos_e_receitas_em_uso(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors();
        $id = fn (string $k, int $n) => DemoDataService::idFor(Seed::TENANT_A, $k, $n);
        $this->get('/configuracoes/medicamentos')->assertOk()->assertSee('Amoxicilina (demo)')->assertSee('Controle especial');
        $this->get('/pacientes/'.$id('PT', 8))->assertOk()->assertSee('Medicamentos em uso')->assertSee('Pimobendan (demo)')->assertSee('1 cápsula (1,25 mg)');
        $this->get('/pacientes/'.$id('PT', 3))->assertSee('Meloxicam suspensão (demo)')->assertSee('2,5 mL (1,25 mg)')->assertSee('1 gota em cada olho');
        $this->get('/documentos/'.$id('DC', 2))->assertOk()->assertSee('Medicamentos prescritos')->assertSee('0,1 mg/kg × 12,3 kg');
        $this->get('/documentos/'.$id('DC', 2).'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/busca?q=pimobendan')->assertOk()->assertSee('Toby');
        $this->post('/sair');

        // Veterinário: Bento tem alergia a penicilina → aviso ao prescrever Amoxicilina (classe penicilinas).
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/pacientes/'.$id('PT', 1).'/receitas', ['action' => 'preview', 'rows' => [['medication_id' => $id('MD', 1)]]]);
        $this->get('/pacientes/'.$id('PT', 1).'/receitas/nova')->assertSee('o paciente tem alergia registrada')->assertSee('1 ½ comprimido (375 mg)');
        $this->post('/sair');

        $this->loginInto('ana', Seed::TENANT_A);
        $pdf = $this->sys(fn () => DB::table('issued_documents')->where('id', $id('DC', 1))->value('pdf_path'));
        $this->post(self::URL.'/remover', ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->demoRows('medications', Seed::TENANT_A));
        $this->assertSame(0, $this->sys(fn () => DB::table('prescription_items')->where('tenant_id', Seed::TENANT_A)->count()));
        Storage::disk('local')->assertMissing((string) $pdf);
    }

    /** Demonstração v6: rascunho no painel (guia 2026-10-21-navegacao-reorganizada.html, seção 5). */
    public function test_demo_v6_rascunho_aparece_no_painel_e_na_ficha(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors();
        $this->get('/painel')->assertOk()->assertSeeInOrder(['>1<', 'atendimento em rascunho'], false)
            ->assertSee('Prontuários em rascunho')->assertSee('Bidu');
        $bidu = DemoDataService::idFor(Seed::TENANT_A, 'PT', 10);
        $this->get('/pacientes/'.$bidu)->assertOk()->assertSee('Coceira nas orelhas')->assertSee('Rascunho');
    }

    /** Demonstração v5: fundos de documento + exames estruturados (guia 2026-10-21-fundos-exames-estruturados.html, seção 5). */
    public function test_demo_v5_fundos_de_documento_e_exames_estruturados(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post(self::URL)->assertSessionHasNoErrors();
        $id = fn (string $k, int $n) => DemoDataService::idFor(Seed::TENANT_A, $k, $n);

        // Fundos: timbrado PDF e receituário Word fictícios, já em uso.
        $this->get('/configuracoes/fundos-de-documento')->assertSee('papel-timbrado-demonstracao.pdf')->assertSee('receituario-demonstracao.docx')->assertSee('Em uso');
        $this->get('/configuracoes/fundos-de-documento/'.$id('BG', 1).'/previa')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(self::URL)->assertSee('Papel timbrado (PDF) e receituário (Word) fictícios');
        // Receita para o Bento sai sobre o receituário.
        $bento = $id('PT', 1);
        $this->get("/pacientes/{$bento}/documentos/novo")->assertOk(); // cria os modelos padrão
        $tpl = $this->sys(fn () => DB::table('document_templates')->where('tenant_id', Seed::TENANT_A)->where('name', 'Receita simples')->value('id'));
        $this->post("/pacientes/{$bento}/documentos", ['template_id' => $tpl, 'title' => 'Receituário', 'body' => 'Medicamento fictício — 1 comprimido a cada 12 h.'])->assertSessionHasNoErrors();
        $this->assertSame($id('BG', 2), $this->sys(fn () => DB::table('issued_documents')->where('tenant_id', Seed::TENANT_A)->where('patient_id', $bento)->value('background_id')));

        // Exames estruturados: série da creatinina do Simba, hemograma do Bento com leucócitos acima da faixa.
        $this->get('/exames/'.$id('EX', 1))->assertSee('Creatinina')->assertSee('3,8')->assertSee('Acima da faixa')->assertSee('Evolução:', false);
        $this->get('/exames/'.$id('EX', 10))->assertSee('Leucócitos')->assertSee('value="19.800"', false)->assertSee('1 fora da faixa');
        $this->get('/exames')->assertSee('Bento · Hemograma completo')->assertSee('fora da faixa');
        $this->get('/busca?q=leucocitos')->assertSee('acima da faixa');

        $paths = $this->sys(fn () => DB::table('document_backgrounds')->where('tenant_id', Seed::TENANT_A)->pluck('render_path')->all());
        $this->post(self::URL.'/remover', ['confirm' => '1'])->assertSessionHasNoErrors();
        foreach (['document_backgrounds', 'exam_result_values', 'exam_reference_ranges'] as $table) {
            $this->assertSame(0, $this->demoRows($table, Seed::TENANT_A), $table);
        }
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_demo_nao_substitui_fundo_real_da_clinica(): void
    {
        config(['fauvovet.demo_data' => true]);
        $this->loginInto('ana', Seed::TENANT_A);
        $pdf = PdfRenderer::html('<p>Timbre real fictício</p>');
        $this->post('/configuracoes/fundos-de-documento', ['kind' => 'letterhead', 'file' => UploadedFile::fake()->createWithContent('timbre-real.pdf', $pdf)])->assertSessionHasNoErrors();
        $this->post(self::URL)->assertSessionHasNoErrors();
        $this->get('/configuracoes/fundos-de-documento')->assertSee('timbre-real.pdf')->assertDontSee('papel-timbrado-demonstracao.pdf')->assertSee('receituario-demonstracao.docx');
        $this->post(self::URL.'/remover', ['confirm' => '1']);
        $this->get('/configuracoes/fundos-de-documento')->assertSee('timbre-real.pdf')->assertDontSee('receituario-demonstracao.docx');
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
