<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Models\AuditEvent;
use App\Models\ExamRequest;
use App\Models\ExamType;
use App\Services\DocumentService;
use App\Services\ExamService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Exames (Fase 3): pedido → etapas → resultado (texto/laudo) → revisão assinada; cancelamento, busca, permissões e isolamento. */
final class ExamTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const JABU = '01K7FV0000000000000PTB0001';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function type(string $tenant, string $name): ExamType
    {
        return app(TenantContext::class)->runForTenant($tenant, function () use ($name) {
            app(ExamService::class)->ensureDefaults();

            return ExamType::query()->where('name', $name)->firstOrFail();
        });
    }

    private function requestExam(string $patient, string $typeName, string $tenant = Seed::TENANT_A): ExamRequest
    {
        $this->post("/pacientes/{$patient}/exames", ['exam_type_id' => $this->type($tenant, $typeName)->id, 'lab_name' => 'Lab Fictício', 'request_notes' => 'Jejum de 8 h'])
            ->assertRedirect()->assertSessionHasNoErrors();

        return $this->sys(fn () => ExamRequest::query()->latest('requested_at')->latest('id')->firstOrFail());
    }

    public function test_fluxo_completo_do_pedido_a_revisao(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR)->assertSee('Exames')->assertSee('Solicitar exame')->assertSee('Hemograma completo');
        $exam = $this->requestExam(self::THOR, 'Bioquímica renal (ureia e creatinina)');
        $this->assertSame('requested', $exam->status);
        $this->get('/exames')->assertSee('Thor · Bioquímica renal')->assertSee('Solicitado');

        $this->post("/exames/{$exam->id}/situacao", ['status' => 'reviewed'])->assertSessionHasErrors('status'); // revisar é ação própria
        $this->post("/exames/{$exam->id}/situacao", ['status' => 'collected'])->assertSessionHasNoErrors();
        $this->post("/exames/{$exam->id}/situacao", ['status' => 'requested'])->assertSessionHasErrors('status'); // não volta
        $this->post("/exames/{$exam->id}/revisar", ['interpretation' => 'cedo demais', 'confirm' => '1'])->assertSessionHasErrors('interpretation');

        $laudo = app(DocumentService::class)->renderHtml('<p>Laudo fictício: creatinina 2,9 mg/dL; SDMA aumentado.</p>');
        $this->post("/exames/{$exam->id}/resultado", ['result_text' => 'Creatinina 2,9 mg/dL (fictício).', 'file' => UploadedFile::fake()->createWithContent('laudo.pdf', $laudo)])
            ->assertSessionHasNoErrors();
        $this->sys(fn () => $exam->refresh());
        $this->assertSame('received', $exam->status);
        $this->assertNotNull($exam->collected_at);
        $this->assertNull($exam->sent_at); // etapa pulada fica sem data
        $this->assertNotNull($exam->result_attachment_id);

        // Busca clínica encontra o resultado do exame e o texto do laudo anexado.
        $this->get('/busca?q=creatinina')->assertSee('Exame: Bioquímica renal (ureia e creatinina) — Resultado recebido');
        $this->get('/busca?q=sdma')->assertSee('Anexo PDF: Laudo — Bioquímica renal');

        $this->get("/exames/{$exam->id}")->assertSee('Revisar e assinar')->assertSee('Baixar laudo')->assertSee('Texto pesquisável');
        $this->post("/exames/{$exam->id}/revisar", ['interpretation' => 'Azotemia leve (fictício).'])->assertSessionHasErrors('confirm');
        $this->post("/exames/{$exam->id}/revisar", ['interpretation' => 'Azotemia leve; repetir em 30 dias (fictício).', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->sys(fn () => $exam->refresh());
        $this->assertSame(['reviewed', Seed::USERS['bruno']['id']], [$exam->status, $exam->reviewed_by]);
        $this->get("/exames/{$exam->id}")->assertSee('Revisado e assinado por Bruno')->assertDontSee('Registrar resultado');
        $this->post("/exames/{$exam->id}/resultado", ['result_text' => 'mudança'])->assertSessionHasErrors('result_text');
        $this->get('/exames?situacao=reviewed')->assertSee('Thor · Bioquímica renal');

        foreach (['exam.requested', 'exam.status_changed', 'exam.result_registered', 'exam.reviewed', 'exam.viewed'] as $action) {
            $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', $action)->exists()), $action);
        }
        $this->expectException(ClinicalRecordLocked::class);
        app(TenantContext::class)->runForTenant(Seed::TENANT_A, fn () => ExamRequest::query()->findOrFail($exam->id)->forceFill(['interpretation' => 'x'])->save());
    }

    public function test_cancelar_e_catalogo(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $exam = $this->requestExam(self::THOR, 'Urinálise');
        $this->post("/exames/{$exam->id}/cancelar", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/exames/{$exam->id}/cancelar", ['reason' => 'Tutor desistiu'])->assertSessionHasNoErrors();
        $this->get("/exames/{$exam->id}")->assertSee('Cancelado em')->assertSee('Tutor desistiu')->assertDontSee('Atualizar situação');

        $this->get('/configuracoes/exames')->assertOk()->assertSee('Ecocardiograma');
        $this->post('/configuracoes/exames', ['name' => 'Teste rápido FIV/FeLV (fictício)', 'category' => 'lab', 'sample' => 'Sangue'])->assertSessionHasNoErrors();
        $t = $this->type(Seed::TENANT_A, 'Teste rápido FIV/FeLV (fictício)');
        $this->put("/configuracoes/exames/{$t->id}", ['name' => $t->name, 'category' => 'lab', 'active' => '0'])->assertSessionHasNoErrors();
        $this->post('/pacientes/'.self::THOR.'/exames', ['exam_type_id' => $t->id])->assertSessionHasErrors('exam_type_id'); // desativado
    }

    public function test_permissoes(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $exam = $this->requestExam(self::THOR, 'Hemograma completo');
        $this->post('/sair');
        foreach (['carla', 'davi'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get('/exames')->assertForbidden();
            $this->get("/exames/{$exam->id}")->assertForbidden();
            $this->post('/pacientes/'.self::THOR.'/exames', ['exam_type_id' => $exam->exam_type_id])->assertForbidden();
            $this->get('/configuracoes/exames')->assertForbidden();
            $this->post('/sair');
        }
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_exames(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $exam = $this->requestExam(self::THOR, 'Hemograma completo');
        $this->post('/sair');
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/exames')->assertDontSee('Thor');
        foreach (['get' => "/exames/{$exam->id}"] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post("/exames/{$exam->id}/situacao", ['status' => 'collected'])->assertNotFound();
        $this->post("/exames/{$exam->id}/cancelar", ['reason' => 'invasão'])->assertNotFound();
        $this->post('/pacientes/'.self::JABU.'/exames', ['exam_type_id' => $exam->exam_type_id])->assertSessionHasErrors('exam_type_id'); // tipo da A
        $this->assertSame('requested', $this->sys(fn () => ExamRequest::query()->findOrFail($exam->id)->status));

        $this->expectException(QueryException::class); // FK composta: pedido na B com paciente da A
        $typeB = $this->type(Seed::TENANT_B, 'Hemograma completo');
        $this->sys(fn () => DB::table('exam_requests')->insert(['id' => '01K7FV0000000000000EXX0001', 'tenant_id' => Seed::TENANT_B, 'patient_id' => self::THOR,
            'exam_type_id' => $typeB->id, 'status' => 'requested', 'requested_by' => Seed::USERS['diana']['id'], 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()]));
    }
}
