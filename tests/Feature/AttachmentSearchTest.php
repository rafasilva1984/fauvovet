<?php

namespace Tests\Feature;

use App\Files\TextExtractor;
use App\Models\Attachment;
use App\Services\DocumentService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

/** Texto de anexos na busca clínica (§3.3 ex. 4 e 10): PDF/DOCX pesquisáveis, imagem "sem texto pesquisável", pendentes e isolamento. */
final class AttachmentSearchTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const KIWI = '01K7FV0000000000000PTA0003'; // calopsita

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private static function pdf(string $text): string
    {
        return app(DocumentService::class)->renderHtml('<p>'.e($text).'</p>');
    }

    private static function docx(string $text): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private static function png(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function upload(string $patient, string $name, string $content, string $title): TestResponse
    {
        return $this->post("/pacientes/{$patient}/anexos", ['file' => UploadedFile::fake()->createWithContent($name, $content), 'category' => 'exam', 'title' => $title]);
    }

    public function test_extrator_pdf_docx_imagem_e_arquivo_corrompido(): void
    {
        $this->assertSame(['status' => 'indexed', 'text' => 'Biomicroscopia com flare em câmara anterior (fictício).'], TextExtractor::extract(self::pdf('Biomicroscopia com flare em câmara anterior (fictício).'), 'pdf'));
        $this->assertSame('indexed', TextExtractor::extract(self::docx('Sorologia reagente para Encephalitozoon (fictício).'), 'docx')['status']);
        $this->assertSame('no_text', TextExtractor::extract(self::png(), 'png')['status']);
        $this->assertSame('failed', TextExtractor::extract("%PDF-1.4\nlixo", 'pdf')['status']);
        $this->assertSame('too_large', TextExtractor::extract(str_repeat('a', TextExtractor::MAX_BYTES + 1), 'pdf')['status']);
    }

    public function test_texto_do_laudo_anexado_aparece_na_busca_com_origem(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        // Exemplo 4: termo só existe no laudo anexado da ave.
        $this->upload(self::KIWI, 'laudo.pdf', self::pdf('Laudo oftalmológico fictício: uveíte anterior; tonometria de rebote 9 mmHg.'), 'Laudo oftalmológico')->assertSessionHasNoErrors();
        $this->upload(self::THOR, 'sorologia.docx', self::docx('Resultado fictício: Leptospira não reagente.'), 'Sorologia')->assertSessionHasNoErrors();
        $this->upload(self::THOR, 'foto.png', self::png(), 'Foto da lesão na pata')->assertSessionHasNoErrors();

        $this->get('/busca?q=uveite&especie=bird')->assertSee('/pacientes/'.self::KIWI, false)->assertSee('Anexo PDF: Laudo oftalmológico')->assertSee('<mark>uveíte</mark>', false);
        $this->get('/busca?q=tonometria&origem=attachment')->assertSee('/pacientes/'.self::KIWI, false);
        $this->get('/busca?q=leptospira')->assertSee('Anexo DOCX: Sorologia');
        // Exemplo 10: imagem aparece pelo título, marcada como sem texto pesquisável.
        $this->get('/busca?q=lesao+pata')->assertSee('Foto da lesão na pata — Sem texto pesquisável');
        $this->get('/pacientes/'.self::THOR)->assertSee('Texto pesquisável na Busca clínica')->assertSee('Sem texto pesquisável (imagem)');

        // Remover o anexo tira o texto da busca.
        $laudo = $this->sys(fn () => Attachment::query()->where('title', 'Laudo oftalmológico')->firstOrFail());
        $this->post("/anexos/{$laudo->id}/remover", ['reason' => 'Enviado no paciente errado'])->assertSessionHasNoErrors();
        $this->get('/busca?q=tonometria')->assertSee('Nada encontrado');
    }

    public function test_anexos_antigos_pendentes_sao_lidos_pelo_botao(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload(self::THOR, 'antigo.pdf', self::pdf('Ecocardiograma fictício: insuficiência mitral discreta.'), 'Eco antigo');
        $this->get('/busca')->assertOk(); // índice da clínica já construído (versão atual)
        // Simula anexo enviado antes desta versão.
        $this->sys(fn () => DB::table('attachments')->where('tenant_id', Seed::TENANT_A)->update(['text_status' => null, 'extracted_text' => null]));
        $this->post('/busca/reindexar')->assertForbidden(); // reconstruir é do titular
        $this->get('/busca?q=ecocardiograma')->assertSee('ainda não teve o texto lido')->assertSee('Ler anexos pendentes');
        $this->post('/busca/anexos')->assertRedirect('/busca')->assertSessionHasNoErrors();
        $this->get('/busca?q=ecocardiograma')->assertSee('Anexo PDF: Eco antigo')->assertDontSee('ainda não teve o texto lido');
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_texto_de_anexo(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload(self::THOR, 'segredo.pdf', self::pdf('Achado exclusivo fictício: xantomatose rara.'), 'Laudo A');
        $this->post('/sair');
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/busca?q=xantomatose')->assertSee('Nada encontrado')->assertDontSee('Laudo A');
        $this->post('/busca/anexos')->assertSessionHasNoErrors(); // lê só pendentes da própria clínica
        $this->assertSame('indexed', $this->sys(fn () => Attachment::query()->where('title', 'Laudo A')->value('text_status')));
    }
}
