<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Documents\Placeholders;
use App\Models\AuditEvent;
use App\Models\DocumentTemplate;
use App\Models\IssuedDocument;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Documentos clínicos: modelos, emissão em PDF preservado, permissões, anulação e isolamento A/B. */
final class DocumentTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const JABU = '01K7FV0000000000000PTB0001';

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

    private function template(string $tenantId, string $name): DocumentTemplate
    {
        return app(TenantContext::class)->runForTenant($tenantId, function () use ($name) {
            app(DocumentService::class)->ensureDefaultTemplates();

            return DocumentTemplate::query()->where('name', $name)->firstOrFail();
        });
    }

    private function issue(string $name, array $over = [], string $patient = self::THOR, string $tenant = Seed::TENANT_A): ?IssuedDocument
    {
        $t = $this->template($tenant, $name);
        $this->post("/pacientes/{$patient}/documentos", ['template_id' => $t->id, 'title' => $t->title, 'body' => 'Texto fictício de teste para '.$name.'.', ...$over]);

        return $this->sys(fn () => IssuedDocument::query()->latest('issued_at')->latest('id')->first());
    }

    public function test_modelo_vem_preenchido_com_dados_do_paciente_e_do_atendimento(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $t = $this->template(Seed::TENANT_A, 'Encaminhamento');
        $this->get('/pacientes/'.self::THOR.'/documentos/novo?modelo='.$t->id.'&atendimento='.self::ENC_THOR)->assertOk()
            ->assertSee('Thor')->assertSee('Cão')->assertSee('Marina Souza (fictícia)')
            ->assertSee('Retorno: prurido reduziu com o tratamento.')->assertSee('Dermatite atópica')
            ->assertDontSee('{{paciente.nome}}', false);
    }

    public function test_emitir_gera_pdf_privado_com_hash_e_auditoria(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $doc = $this->issue('Receita simples', ['body' => "Uso veterinário.\n\n1. Medicamento fictício — 1 comprimido a cada 12 h por 7 dias."]);
        $this->assertNotNull($doc);
        $this->assertSame(['prescription', self::THOR, Seed::TENANT_A], [$doc->category, $doc->patient_id, $doc->tenant_id]);
        $this->assertStringStartsWith('documents/'.Seed::TENANT_A.'/', $doc->pdf_path); // pasta por clínica, disco privado
        $bytes = Storage::disk('local')->get($doc->pdf_path);
        $this->assertStringStartsWith('%PDF', (string) $bytes);
        $this->assertSame(hash('sha256', (string) $bytes), $doc->pdf_sha256);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{12}$/', $doc->verification_code);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'document.issued')->where('target_id', $doc->id)->exists()));

        $this->get("/documentos/{$doc->id}")->assertOk()->assertSee('Medicamento fictício')->assertSee($doc->verification_code);
        $res = $this->get("/documentos/{$doc->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($bytes, $res->getContent());
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'document.downloaded')->where('target_id', $doc->id)->exists()));
        $this->get('/pacientes/'.self::THOR)->assertSee('Receituário');
    }

    public function test_lembretes_entre_colchetes_pedem_confirmacao(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $t = $this->template(Seed::TENANT_A, 'Receita simples');
        $payload = ['template_id' => $t->id, 'title' => 'Receituário', 'body' => "1. Amoxicilina fictícia\n[Dose] — via [via]."];
        $this->post('/pacientes/'.self::THOR.'/documentos', $payload)->assertSessionHasErrors(['body', 'keep_brackets']);
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->post('/pacientes/'.self::THOR.'/documentos', [...$payload, 'keep_brackets' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->sys(fn () => IssuedDocument::query()->count()));
    }

    public function test_pdf_adulterado_no_disco_nao_e_entregue(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $doc = $this->issue('Atestado de saúde');
        Storage::disk('local')->put((string) $doc?->pdf_path, '%PDF-falso');
        $this->get("/documentos/{$doc?->id}/pdf")->assertStatus(500);
    }

    public function test_texto_e_escapado_no_pdf(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $doc = $this->issue('Orientações pós-consulta', ['body' => 'Dar água <script>alert(1)</script> & <b>repouso</b>.']);
        $html = view('documents.pdf', [
            'id' => $doc?->id, 'code' => 'X', 'title' => 'T', 'body' => (string) $doc?->body, 'category' => 'guidance', 'issuedAt' => now(),
            'tenant' => $this->sys(fn () => Tenant::query()->find(Seed::TENANT_A)), 'unit' => null,
            'issuer' => User::query()->find(Seed::USERS['bruno']['id']), 'patient' => $this->sys(fn () => Patient::query()->find(self::THOR)),
            'values' => array_fill_keys(array_keys(Placeholders::LIST), ''), 'primary' => '#0e5e66',
        ])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<b>repouso</b>', $html);
    }

    public function test_receita_exige_crmv_e_variavel_desconhecida_e_recusada(): void
    {
        DB::table('users')->where('id', Seed::USERS['bruno']['id'])->update(['crmv' => null]);
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->issue('Receita simples');
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->get('/pacientes/'.self::THOR.'/documentos/novo')->assertSee('Seu CRMV não está cadastrado');

        $this->put('/perfil', ['name' => 'Bruno Veterinário (fictício)', 'crmv' => 'crmv-sp 12345'])->assertSessionHasNoErrors();
        $this->assertSame('CRMV-SP 12345', User::query()->find(Seed::USERS['bruno']['id'])?->crmv);
        $this->put('/perfil', ['name' => 'Bruno', 'crmv' => '<b>x</b>'])->assertSessionHasErrors('crmv');

        $this->issue('Receita simples', ['body' => 'Para {{paciente.nome}} e {{senha.admin}}'])?->id;
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->issue('Receita simples');
        $this->assertSame(1, $this->sys(fn () => IssuedDocument::query()->count()));
    }

    public function test_modelo_editado_ganha_versao_e_documento_emitido_nao_muda(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $doc = $this->issue('Atestado de saúde', ['body' => 'Texto da versão 1.']);
        $t = $this->template(Seed::TENANT_A, 'Atestado de saúde');
        $this->put("/configuracoes/modelos-de-documento/{$t->id}", ['name' => $t->name, 'category' => 'certificate', 'title' => $t->title, 'body' => 'Novo texto {{paciente.nome}}.', 'active' => '1'])->assertSessionHasNoErrors();
        $this->put("/configuracoes/modelos-de-documento/{$t->id}", ['name' => $t->name, 'category' => 'certificate', 'title' => $t->title, 'body' => 'Ruim {{paciente.senha}}', 'active' => '1'])->assertSessionHasErrors('body');
        $this->assertSame(2, $this->sys(fn () => DocumentTemplate::query()->find($t->id)?->version));
        $this->assertSame(['Texto da versão 1.', 1], $this->sys(fn () => [IssuedDocument::query()->find($doc?->id)?->body, IssuedDocument::query()->find($doc?->id)?->template_version]));
        $this->get('/configuracoes/modelos-de-documento')->assertOk()->assertSee('versão 2');
    }

    public function test_permissoes_por_papel(): void
    {
        // Assistente: emite orientações, não receita. Recepção: nada de documentos clínicos.
        $this->sys(fn () => DB::table('memberships')->where('id', $this->membershipId('carla', Seed::TENANT_A))->update(['roles' => json_encode(['clinical_assistant'])]));
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR.'/documentos/novo')->assertOk()->assertSee('Orientações pós-consulta')->assertDontSee('Receita simples');
        $t = $this->template(Seed::TENANT_A, 'Receita simples');
        $this->post('/pacientes/'.self::THOR.'/documentos', ['template_id' => $t->id, 'title' => 'Título', 'body' => 'Texto qualquer'])->assertForbidden();
        $this->assertNotNull($this->issue('Orientações pós-consulta'));
        $this->get('/configuracoes/modelos-de-documento')->assertForbidden();
        $this->post('/sair');

        $this->loginInto('davi', Seed::TENANT_A);
        $doc = $this->sys(fn () => IssuedDocument::query()->first());
        $this->get("/documentos/{$doc?->id}")->assertForbidden();
        $this->get("/documentos/{$doc?->id}/pdf")->assertForbidden();
        $this->get('/pacientes/'.self::THOR.'/documentos/novo')->assertForbidden();
    }

    public function test_anular_so_quem_emitiu_ou_titular_e_documento_e_imutavel(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $doc = $this->issue('Atestado de saúde');
        $this->post('/sair');
        $this->loginInto('eva', Seed::TENANT_A);
        $this->post("/documentos/{$doc?->id}/anular", ['reason' => 'Teste'])->assertForbidden();
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post("/documentos/{$doc?->id}/anular", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/documentos/{$doc?->id}/anular", ['reason' => 'Emitido para o paciente errado'])->assertRedirect("/documentos/{$doc?->id}");
        $this->get("/documentos/{$doc?->id}")->assertSee('Documento anulado')->assertSee('Emitido para o paciente errado');
        $this->assertTrue(Storage::disk('local')->exists((string) $doc?->pdf_path)); // PDF original guardado

        app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () use ($doc) {
            $d = IssuedDocument::query()->findOrFail($doc?->id);
            $d->body = 'reescrito';
            try {
                $d->save();
                $this->fail('Documento emitido foi alterado.');
            } catch (ClinicalRecordLocked) {
                $this->assertTrue(true);
            }
            $this->expectException(ClinicalRecordLocked::class);
            IssuedDocument::query()->findOrFail($doc?->id)->delete();
        });
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_documento_e_modelo_de_outra_clinica(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $doc = $this->issue('Atestado de saúde');
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get("/documentos/{$doc?->id}")->assertNotFound();
        $this->get("/documentos/{$doc?->id}/pdf")->assertNotFound();
        $this->post("/documentos/{$doc?->id}/anular", ['reason' => 'invasão'])->assertNotFound();
        $this->get('/pacientes/'.self::THOR.'/documentos/novo')->assertNotFound();
        $tA = $this->template(Seed::TENANT_A, 'Atestado de saúde');
        $this->post('/pacientes/'.self::JABU.'/documentos', ['template_id' => $tA->id, 'title' => 'Título', 'body' => 'Texto qualquer'])->assertNotFound();
        $tB = $this->template(Seed::TENANT_B, 'Atestado de saúde');
        $this->post('/pacientes/'.self::JABU.'/documentos', ['template_id' => $tB->id, 'encounter_id' => self::ENC_THOR, 'title' => 'Título', 'body' => 'Texto qualquer'])->assertNotFound();
        $this->get("/configuracoes/modelos-de-documento/{$tA->id}/editar")->assertNotFound();
        $this->assertSame(1, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->assertNull($this->sys(fn () => IssuedDocument::query()->find($doc?->id)?->voided_at));
    }
}
