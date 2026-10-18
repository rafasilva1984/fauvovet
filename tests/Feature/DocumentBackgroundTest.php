<?php

namespace Tests\Feature;

use App\Documents\DocxLetterhead;
use App\Documents\PdfOverlay;
use App\Documents\PdfRenderer;
use App\Documents\PdfXrefFlattener;
use App\Models\AuditEvent;
use App\Models\DocumentBackground;
use App\Models\DocumentTemplate;
use App\Models\IssuedDocument;
use App\Services\DemoDataService;
use App\Services\DocumentService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/** Fundos de documento (ADR-020): papel timbrado e receituário em PDF/DOCX/imagem, escolha por modelo/emissão e isolamento. */
final class DocumentBackgroundTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const URL = '/configuracoes/fundos-de-documento';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function letterheadPdf(string $text = 'Clinica Ficticia Timbre'): string
    {
        return PdfRenderer::html('<html><head><style>@page{margin:0}body{margin:0}</style></head><body><div style="position:absolute;top:8mm;left:15mm;font-size:16pt">'.$text.'</div></body></html>');
    }

    private function upload(string $kind, string $bytes, string $name): TestResponse
    {
        return $this->post(self::URL, ['kind' => $kind, 'file' => UploadedFile::fake()->createWithContent($name, $bytes)]);
    }

    private function current(string $tenant, string $kind): ?DocumentBackground
    {
        return app(TenantContext::class)->runForTenant($tenant, fn () => DocumentBackground::query()->current()->where('kind', $kind)->first());
    }

    private function issue(string $templateName, array $over = []): IssuedDocument
    {
        $t = app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () use ($templateName) {
            app(DocumentService::class)->ensureDefaultTemplates();

            return DocumentTemplate::query()->where('name', $templateName)->firstOrFail();
        });
        $this->post('/pacientes/'.self::THOR.'/documentos', ['template_id' => $t->id, 'title' => $t->title, 'body' => 'Texto fictício de teste com fundo.', ...$over])
            ->assertSessionHasNoErrors();

        return $this->sys(fn () => IssuedDocument::query()->latest('issued_at')->latest('id')->firstOrFail());
    }

    private function text(string $pdf): string
    {
        return (new Parser)->parseContent($pdf)->getText();
    }

    public function test_enviar_pdf_vira_fundo_padrao_e_documento_sai_sobre_o_timbre(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get(self::URL)->assertOk()->assertSee('Papel timbrado')->assertSee('Receituário')->assertSee('Não enviado');
        $this->upload('letterhead', $this->letterheadPdf(), 'timbre.pdf')->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, 'fundo padrão'));
        $bg = $this->current(Seed::TENANT_A, 'letterhead');
        $this->assertNotNull($bg);
        $this->assertStringStartsWith('backgrounds/'.Seed::TENANT_A.'/', $bg->source_path);
        Storage::disk('local')->assertExists([$bg->source_path, $bg->render_path]);
        $this->assertSame([40, 30, 20], [$bg->margin_top, $bg->margin_bottom, $bg->margin_side]);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'document_background.uploaded')->where('target_id', $bg->id)->exists()));

        // Atestado (automático → papel timbrado): o PDF tem o timbre E o texto; o cabeçalho padrão sai.
        $doc = $this->issue('Atestado de saúde');
        $this->assertSame($bg->id, $doc->background_id);
        $text = $this->text((string) Storage::disk('local')->get($doc->pdf_path));
        $this->assertStringContainsString('Clinica Ficticia Timbre', $text);
        $this->assertStringContainsString('Texto fictício de teste com fundo.', $text);
        $this->assertStringContainsString($doc->verification_code, $text);
        $this->assertStringNotContainsString('Clínica Veterinária Aurora (fictícia)', $text); // cabeçalho padrão não aparece

        // Receita também usa o timbrado enquanto não há receituário.
        $this->assertSame($bg->id, $this->issue('Receita simples')->background_id);
    }

    public function test_receituario_nas_receitas_timbrado_nos_demais_e_escolha_manual(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('letterhead', $this->letterheadPdf('Timbre Geral'), 'timbre.pdf')->assertSessionHasNoErrors();
        $this->upload('prescription', $this->letterheadPdf('Receituario Proprio'), 'receituario.pdf')->assertSessionHasNoErrors();
        $lh = $this->current(Seed::TENANT_A, 'letterhead');
        $rx = $this->current(Seed::TENANT_A, 'prescription');

        $receita = $this->issue('Receita simples');
        $this->assertSame($rx->id, $receita->background_id);
        $this->assertStringContainsString('Receituario Proprio', $this->text((string) Storage::disk('local')->get($receita->pdf_path)));
        $this->assertSame($lh->id, $this->issue('Encaminhamento')->background_id);
        // Escolha na emissão: atestado no receituário; receita sem fundo (cabeçalho padrão volta).
        $this->assertSame($rx->id, $this->issue('Atestado de saúde', ['background' => 'prescription'])->background_id);
        $semFundo = $this->issue('Receita simples', ['background' => 'none']);
        $this->assertNull($semFundo->background_id);
        $this->assertStringContainsString('Clínica Veterinária Aurora (fictícia)', $this->text((string) Storage::disk('local')->get($semFundo->pdf_path)));
        $this->post('/pacientes/'.self::THOR.'/documentos', ['template_id' => 'x', 'background' => 'qualquer'])->assertSessionHasErrors('background');
    }

    public function test_modelo_guarda_o_fundo_escolhido_e_a_emissao_vem_preenchida(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->upload('prescription', $this->letterheadPdf(), 'receituario.pdf')->assertSessionHasNoErrors();
        $t = app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () {
            app(DocumentService::class)->ensureDefaultTemplates();

            return DocumentTemplate::query()->where('name', 'Orientações pós-consulta')->firstOrFail();
        });
        $this->get('/configuracoes/modelos-de-documento/'.$t->id.'/editar')->assertSee('Fundo do documento')->assertSee('Papel timbrado — ainda não enviado');
        $this->put('/configuracoes/modelos-de-documento/'.$t->id, ['name' => $t->name, 'category' => $t->category, 'title' => $t->title, 'body' => $t->body, 'background' => 'prescription', 'active' => '1'])
            ->assertSessionHasNoErrors();
        $t = $this->sys(fn () => $t->fresh());
        $this->assertSame(['prescription', 2], [$t->background, $t->version]); // trocar o fundo gera nova versão
        $this->get('/pacientes/'.self::THOR.'/documentos/novo?modelo='.$t->id)->assertSee('<option value="prescription" selected', false);
    }

    public function test_pdf_com_tabela_comprimida_e_achatado_e_aceito(): void
    {
        $bytes = (string) file_get_contents(base_path('tests/fixtures/timbrado-ficticio-xref-comprimida.pdf'));
        $this->assertTrue(PdfXrefFlattener::needsFlattening($bytes));
        $this->assertFalse(PdfOverlay::readable($bytes)); // o leitor gratuito do FPDI recusa o original…
        $flat = PdfXrefFlattener::flatten($bytes);
        $this->assertTrue(PdfOverlay::readable($flat));    // …e aceita o achatado
        $this->assertStringStartsWith($bytes === '' ? 'x' : substr(rtrim($bytes), 0, 50), $flat); // original intacto no início

        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('letterhead', $bytes, 'timbre-word.pdf')->assertSessionHasNoErrors();
        $doc = $this->issue('Atestado de saúde');
        $text = $this->text((string) Storage::disk('local')->get($doc->pdf_path));
        $this->assertStringContainsString('Clinica Ficticia de Teste', $text);
        $this->assertStringContainsString('Texto fictício de teste com fundo.', $text);
    }

    public function test_docx_usa_cabecalho_rodape_logotipo_e_margens_do_word(): void
    {
        $docx = DemoDataService::letterheadDocx('Clínica Docx Fictícia', 'RECEITUÁRIO', 'Rua Fictícia, 1 — rodapé do Word');
        if ($docx === null) {
            $this->markTestSkipped('ZipArchive indisponível para montar o DOCX de teste.');
        }
        $r = DocxLetterhead::convert($docx);
        $this->assertSame(['top' => 45, 'bottom' => 25, 'side' => 20], $r['margins']);
        $this->assertStringContainsString('Clínica Docx Fictícia', $r['html']);
        $this->assertStringContainsString('rodapé do Word', $r['html']);
        $this->assertStringContainsString('data:image/png;base64,', $r['html']); // logotipo do cabeçalho
        $this->assertStringNotContainsString('Corpo do documento', $r['html']); // o corpo não é timbre

        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('prescription', $docx, 'receituario.docx')->assertSessionHasNoErrors();
        $bg = $this->current(Seed::TENANT_A, 'prescription');
        $this->assertSame([45, 25, 20], [$bg->margin_top, $bg->margin_bottom, $bg->margin_side]);
        $this->assertStringContainsString('aproximadas', (string) $bg->conversion_notes);
        $this->get(self::URL)->assertSee('receituario.docx')->assertSee('aproximadas');
        $preview = $this->get(self::URL.'/'.$bg->id.'/previa')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('Clínica Docx Fictícia', $this->text((string) $preview->getContent()));
        $this->assertStringContainsString('Medicamento fictício', $this->text((string) $preview->getContent()));
    }

    public function test_imagem_vira_pagina_inteira_e_arquivos_invalidos_sao_recusados(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC');
        $this->upload('letterhead', $png, 'timbre.png')->assertSessionHasNoErrors();
        $this->assertStringContainsString('A4 inteira', (string) $this->current(Seed::TENANT_A, 'letterhead')->conversion_notes);

        $this->upload('prescription', '<?php echo 1;', 'receituario.pdf')->assertSessionHasErrors('file');
        $this->upload('prescription', 'texto qualquer', 'receituario.txt')->assertSessionHasErrors('file');
        $encrypted = str_replace('/Root', '/Encrypt 99 0 R /Root', $this->letterheadPdf());
        $this->upload('prescription', $encrypted, 'protegido.pdf')->assertSessionHasErrors('file');
        $this->assertNull($this->current(Seed::TENANT_A, 'prescription'));
        $this->post(self::URL, ['kind' => 'outro', 'file' => UploadedFile::fake()->createWithContent('a.pdf', $this->letterheadPdf())])->assertSessionHasErrors('kind');
    }

    public function test_substituir_aposenta_o_anterior_margens_e_remocao_nao_mudam_documento_emitido(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('letterhead', $this->letterheadPdf('Timbre Antigo'), 'v1.pdf');
        $old = $this->current(Seed::TENANT_A, 'letterhead');
        $doc = $this->issue('Atestado de saúde');
        $hash = $doc->pdf_sha256;

        $this->upload('letterhead', $this->letterheadPdf('Timbre Novo'), 'v2.pdf')->assertSessionHasNoErrors();
        $new = $this->current(Seed::TENANT_A, 'letterhead');
        $this->assertNotSame($old->id, $new->id);
        $this->assertNotNull($this->sys(fn () => DocumentBackground::query()->find($old->id))->retired_at); // aposentado, não apagado
        Storage::disk('local')->assertExists($old->source_path);

        $this->put(self::URL.'/'.$new->id, ['margin_top' => 55, 'margin_bottom' => 35, 'margin_side' => 25])->assertSessionHasNoErrors();
        $fresh = $this->sys(fn () => $new->fresh());
        $this->assertSame([55, 35, 25], [$fresh->margin_top, $fresh->margin_bottom, $fresh->margin_side]);
        $this->put(self::URL.'/'.$new->id, ['margin_top' => 1, 'margin_bottom' => 35, 'margin_side' => 25])->assertSessionHasErrors('margin_top');

        $this->post(self::URL.'/'.$new->id.'/remover')->assertSessionHasNoErrors();
        $this->assertNull($this->current(Seed::TENANT_A, 'letterhead'));
        $this->assertNull($this->issue('Atestado de saúde')->background_id); // sem fundo → cabeçalho padrão
        $doc = $this->sys(fn () => $doc->fresh());
        $this->assertSame([$hash, $old->id], [$doc->pdf_sha256, $doc->background_id]);
    }

    public function test_somente_quem_configura_modelos_envia_fundos(): void
    {
        $this->loginInto('carla', Seed::TENANT_A); // recepção
        $this->get(self::URL)->assertForbidden();
        $this->upload('letterhead', $this->letterheadPdf(), 'timbre.pdf')->assertForbidden();
    }

    #[Group('tenant-isolation')]
    public function test_fundo_de_uma_clinica_nao_aparece_nem_e_usado_na_outra(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('letterhead', $this->letterheadPdf(), 'timbre-a.pdf')->assertSessionHasNoErrors();
        $bgA = $this->current(Seed::TENANT_A, 'letterhead');
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get(self::URL)->assertOk()->assertDontSee('timbre-a.pdf');
        $this->get(self::URL.'/'.$bgA->id.'/previa')->assertNotFound();
        $this->get(self::URL.'/'.$bgA->id.'/original')->assertNotFound();
        $this->put(self::URL.'/'.$bgA->id, ['margin_top' => 50, 'margin_bottom' => 30, 'margin_side' => 20])->assertNotFound();
        $this->post(self::URL.'/'.$bgA->id.'/remover')->assertNotFound();
        $this->assertNull($this->current(Seed::TENANT_B, 'letterhead'));
        $this->assertNull($this->sys(fn () => $bgA->fresh())->retired_at);
    }
}
