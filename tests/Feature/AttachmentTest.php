<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

/** Anexos do prontuário: tipo pelo conteúdo, disco privado, download seguro, remoção, permissões e isolamento. */
final class AttachmentTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const KIWI = '01K7FV0000000000000PTA0003';

    private const ENC_THOR = '01K7FV0000000000000ENA0002';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    private static function png(): string
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private static function docx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function upload(string $name, string $content, array $over = [], string $patient = self::THOR): TestResponse
    {
        return $this->post("/pacientes/{$patient}/anexos", [
            'file' => UploadedFile::fake()->createWithContent($name, $content), 'category' => 'exam', 'title' => '', ...$over,
        ]);
    }

    private function latest(): ?Attachment
    {
        return $this->sys(fn () => Attachment::query()->latest('created_at')->latest('id')->first());
    }

    public function test_envia_pdf_em_disco_privado_com_hash_e_baixa_com_cabecalhos_seguros(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->from('/pacientes/'.self::THOR)->upload('Hemograma Thor.pdf', self::pdf(), ['title' => 'Hemograma completo'])
            ->assertRedirect('/pacientes/'.self::THOR)->assertSessionHasNoErrors();
        $a = $this->latest();
        $this->assertSame(['application/pdf', 'pdf', 'Hemograma completo', 'Hemograma Thor.pdf'], [$a?->mime, $a?->extension, $a?->title, $a?->original_name]);
        $this->assertMatchesRegularExpression('#^attachments/'.Seed::TENANT_A.'/\d{4}/[0-9A-Z]{26}\.pdf$#', (string) $a?->storage_path); // nome aleatório
        $this->assertSame(hash('sha256', self::pdf()), $a?->sha256);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'attachment.uploaded')->where('target_id', $a?->id)->exists()));

        $this->get('/pacientes/'.self::THOR)->assertSee('Hemograma completo')->assertSee('Anexos');
        $res = $this->get("/anexos/{$a?->id}")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::pdf(), $res->getContent());
        $this->assertStringStartsWith('attachment;', (string) $res->headers->get('Content-Disposition')); // PDF de terceiro: só download
        $this->assertStringContainsString('sandbox', (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'attachment.downloaded')->where('target_id', $a?->id)->exists()));
    }

    public function test_imagem_abre_no_navegador_e_docx_e_aceito(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('raio-x.png', self::png(), ['category' => 'image'])->assertSessionHasNoErrors();
        $img = $this->latest();
        $this->assertSame('image/png', $img?->mime);
        $this->assertStringStartsWith('inline;', (string) $this->get("/anexos/{$img?->id}")->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', (string) $this->get("/anexos/{$img?->id}?baixar=1")->headers->get('Content-Disposition'));

        $this->upload('laudo.docx', self::docx(), ['category' => 'document'])->assertSessionHasNoErrors();
        $this->assertSame(['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'], [$this->latest()?->mime, $this->latest()?->extension]);
    }

    /** @return array<string, array{string, string}> */
    public static function arquivosRecusados(): array
    {
        return [
            'PHP renomeado para .pdf' => ['exame.pdf', '<?php system($_GET["c"]); ?>'],
            'HTML com extensão .png' => ['foto.png', '<html><script>alert(1)</script></html>'],
            'PDF com extensão .png (não confere)' => ['foto.png', self::pdf()],
            'SVG (pode conter script)' => ['imagem.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'Executável Windows' => ['laudo.exe', "MZ\x90\x00\x03\x00\x00\x00"],
            'ZIP qualquer como .docx' => ['laudo.docx', "PK\x03\x04qualquer coisa sem word"],
            'Texto puro' => ['notas.txt', 'texto'],
        ];
    }

    #[DataProvider('arquivosRecusados')]
    public function test_tipos_recusados_pelo_conteudo(string $name, string $content): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload($name, $content)->assertSessionHasErrors('file');
        $this->assertSame(0, $this->sys(fn () => Attachment::query()->count()));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_nome_com_caminho_e_tamanho_maximo(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('../../etc/<passwd>.pdf', self::pdf())->assertSessionHasNoErrors();
        $this->assertSame('passwd.pdf', $this->latest()?->original_name);
        config(['fauvovet.attachments.max_kb' => 1]);
        $this->upload('grande.pdf', self::pdf().str_repeat('x', 3000))->assertSessionHasErrors('file');
    }

    public function test_anexo_no_atendimento(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('exame.pdf', self::pdf(), ['encounter_id' => self::ENC_THOR, 'title' => 'Citologia de ouvido'])->assertSessionHasNoErrors();
        $this->assertSame(self::ENC_THOR, $this->latest()?->encounter_id);
        $this->get('/atendimentos/'.self::ENC_THOR)->assertSee('Citologia de ouvido');
        // Atendimento de outro paciente não pode receber o anexo.
        $this->upload('exame.pdf', self::pdf(), ['encounter_id' => self::ENC_THOR], self::KIWI)->assertNotFound();
    }

    public function test_remover_oculta_mantem_arquivo_e_anexo_e_imutavel(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('exame.pdf', self::pdf(), ['title' => 'Exame errado'])->assertSessionHasNoErrors();
        $a = $this->latest();
        $this->post('/sair');
        $this->loginInto('eva', Seed::TENANT_A);
        $this->post("/anexos/{$a?->id}/remover", ['reason' => 'teste'])->assertForbidden();
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post("/anexos/{$a?->id}/remover", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/anexos/{$a?->id}/remover", ['reason' => 'Enviado no paciente errado'])->assertSessionHasNoErrors();
        $this->get('/pacientes/'.self::THOR)->assertDontSee('Exame errado');
        $this->get("/anexos/{$a?->id}")->assertNotFound();
        $this->assertTrue(Storage::disk('local')->exists((string) $a?->storage_path));

        app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () use ($a) {
            $this->expectException(ClinicalRecordLocked::class);
            Attachment::query()->findOrFail($a?->id)->delete();
        });
    }

    public function test_arquivo_adulterado_nao_e_entregue(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('exame.pdf', self::pdf())->assertSessionHasNoErrors();
        $a = $this->latest();
        Storage::disk('local')->put((string) $a?->storage_path, '%PDF-adulterado');
        $this->get("/anexos/{$a?->id}")->assertStatus(500);
    }

    public function test_recepcao_e_financeiro_sem_acesso(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('exame.pdf', self::pdf())->assertSessionHasNoErrors();
        $a = $this->latest();
        $this->post('/sair');
        foreach (['carla', 'davi'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get("/anexos/{$a?->id}")->assertForbidden();
            $this->upload('outro.pdf', self::pdf())->assertForbidden();
            $this->post("/anexos/{$a?->id}/remover", ['reason' => 'x'])->assertForbidden();
            $this->post('/sair');
        }
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_anexo_de_outra_clinica(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->upload('exame.pdf', self::pdf())->assertSessionHasNoErrors();
        $a = $this->latest();
        $this->post('/sair');
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get("/anexos/{$a?->id}")->assertNotFound();
        $this->post("/anexos/{$a?->id}/remover", ['reason' => 'invasão'])->assertNotFound();
        $this->upload('exame.pdf', self::pdf())->assertNotFound(); // paciente da clínica A
        $this->assertNull($this->sys(fn () => Attachment::query()->find($a?->id)?->removed_at));
        $this->assertSame(1, $this->sys(fn () => Attachment::query()->count()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_anexo_cruzado(): void
    {
        $this->expectException(QueryException::class);
        $this->sys(fn () => DB::table('attachments')->insert([
            'id' => '01K7FV0000000000000ATX0001', 'tenant_id' => Seed::TENANT_B, 'patient_id' => self::THOR, // paciente da A
            'category' => 'exam', 'title' => 'x', 'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('0', 64), 'storage_path' => 'x', 'uploaded_by' => Seed::USERS['diana']['id'],
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
