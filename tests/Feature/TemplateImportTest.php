<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

/** Importação de modelos DOCX/PDF (CLAUDE.md §4.2): original preservado, texto convertido, variáveis sugeridas, revisão. */
final class TemplateImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private static function docx(string $text): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $paras = implode('', array_map(fn ($l) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($l, ENT_XML1).'</w:t></w:r></w:p>', explode("\n", $text)));
        $zip->addFromString('word/document.xml', '<w:document><w:body>'.$paras.'</w:body></w:document>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function import(string $name, string $content, string $template = 'Termo importado'): TestResponse
    {
        return $this->post('/configuracoes/modelos-de-documento/importar', [
            'file' => UploadedFile::fake()->createWithContent($name, $content), 'category' => 'consent', 'name' => $template,
        ]);
    }

    public function test_docx_vira_modelo_desativado_com_variaveis_e_original_preservado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $docx = self::docx("TERMO DE AUTORIZAÇÃO (fictício)\nPaciente: __________\nTutor responsável: ________  CPF: ________\nTestemunha: ________");
        $res = $this->import('termo clinica.docx', $docx)->assertSessionHasNoErrors();
        $t = $this->sys(fn () => DocumentTemplate::query()->where('name', 'Termo importado')->firstOrFail());
        $res->assertRedirect("/configuracoes/modelos-de-documento/{$t->id}/editar");

        $this->assertFalse($t->active); // revisão humana antes de usar
        $this->assertStringContainsString('Paciente: {{paciente.nome}}', $t->body);
        $this->assertStringContainsString('Tutor responsável: {{responsavel.nome}} CPF: {{responsavel.documento}}', $t->body);
        $this->assertStringContainsString('Testemunha: [completar]', $t->body);
        $this->assertSame(['termo clinica.docx', hash('sha256', $docx)], [$t->source_name, $t->source_sha256]);
        Storage::disk('local')->assertExists((string) $t->source_path);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'document_template.imported')->exists()));

        $this->get("/configuracoes/modelos-de-documento/{$t->id}/editar")->assertSee('Importado de termo clinica.docx')->assertSee('Baixar original')
            ->assertSee('Paciente → {{paciente.nome}}', false);
        $original = $this->get("/configuracoes/modelos-de-documento/{$t->id}/original")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($docx, $original->getContent());
        $this->get('/configuracoes/modelos-de-documento')->assertSee('importado de termo clinica.docx');

        // Revisado e ativado: vira versão 2 e aparece para emitir; o original continua o mesmo.
        $this->put("/configuracoes/modelos-de-documento/{$t->id}", ['name' => 'Termo importado', 'category' => 'consent', 'title' => 'Termo de autorização',
            'body' => $t->body."\nRevisado pela equipe.", 'active' => '1'])->assertSessionHasNoErrors();
        $this->sys(fn () => $t->refresh());
        $this->assertSame([2, true, hash('sha256', $docx)], [$t->version, $t->active, $t->source_sha256]);
    }

    public function test_pdf_com_texto_e_recusas(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $pdf = app(DocumentService::class)->renderHtml('<p>Atestado fictício. Paciente: ______ Data: ____/____/______</p>');
        $this->import('atestado.pdf', $pdf, 'Atestado importado')->assertSessionHasNoErrors();
        $this->assertStringContainsString('{{paciente.nome}}', (string) $this->sys(fn () => DocumentTemplate::query()->where('name', 'Atestado importado')->value('body')));

        $this->import('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 'Xx')->assertSessionHasErrors('file');
        $this->import('vazio.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n", 'Yy')->assertSessionHasErrors('file');
        $this->import('outro.docx', self::docx('Texto qualquer com conteúdo suficiente para importar.'), 'Atestado importado')->assertSessionHasErrors('name');
        $this->post('/sair');
        $this->loginInto('carla', Seed::TENANT_A);
        $this->import('t.docx', self::docx('Texto qualquer com conteúdo suficiente para importar.'), 'Zz')->assertForbidden();
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_modelo_importado(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->import('termo.docx', self::docx("Termo fictício da clínica A.\nPaciente: ______"), 'Termo da A');
        $t = $this->sys(fn () => DocumentTemplate::query()->where('name', 'Termo da A')->firstOrFail());
        $this->assertStringStartsWith('templates/'.Seed::TENANT_A.'/', (string) $t->source_path);
        $this->post('/sair');
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get("/configuracoes/modelos-de-documento/{$t->id}/original")->assertNotFound();
        $this->get('/configuracoes/modelos-de-documento')->assertDontSee('Termo da A');
    }
}
