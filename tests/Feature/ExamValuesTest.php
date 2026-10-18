<?php

namespace Tests\Feature;

use App\Models\ExamAnalyte;
use App\Models\ExamReferenceRange;
use App\Models\ExamRequest;
use App\Models\ExamResultValue;
use App\Models\ExamType;
use App\Services\ExamService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Exames estruturados: parâmetros com unidade, faixa por espécie (da clínica), situação fora da faixa e histórico. */
final class ExamValuesTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001'; // cão

    private const MIA = '01K7FV0000000000000PTA0002'; // gata

    private const RENAL = 'Bioquímica renal (ureia e creatinina)';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function inA(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runForTenant(Seed::TENANT_A, $fn);
    }

    private function analyte(string $name, string $tenant = Seed::TENANT_A): ExamAnalyte
    {
        return app(TenantContext::class)->runForTenant($tenant, function () use ($name) {
            app(ExamService::class)->ensureDefaults();

            return ExamAnalyte::query()->where('name', $name)->firstOrFail();
        });
    }

    private function requestRenal(string $patient): ExamRequest
    {
        $type = $this->inA(fn () => ExamType::query()->where('name', self::RENAL)->firstOrFail());
        $this->post("/pacientes/{$patient}/exames", ['exam_type_id' => $type->id])->assertSessionHasNoErrors();

        return $this->sys(fn () => ExamRequest::query()->latest('requested_at')->latest('id')->firstOrFail());
    }

    public function test_catalogo_traz_parametros_sem_faixa_e_clinica_cadastra_faixas_por_especie(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $creat = $this->analyte('Creatinina');
        $this->assertSame('mg/dL', $creat->unit);
        $this->assertSame(0, $this->inA(fn () => ExamReferenceRange::query()->count())); // nada de faixa pronta
        $type = $this->inA(fn () => ExamType::query()->where('name', self::RENAL)->firstOrFail());
        $this->get('/configuracoes/exames')->assertSee('2 parâmetros')->assertSee('Parâmetros e faixas');
        $this->get('/configuracoes/exames/'.$type->id.'/parametros')->assertOk()->assertSee('Creatinina')->assertSee('Ureia')->assertSee('Sem faixa');

        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => 'canine', 'ref_min' => '0,5', 'ref_max' => '1,5'])->assertSessionHasNoErrors();
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => '*', 'ref_min' => '0,6', 'ref_max' => '1,6'])->assertSessionHasNoErrors();
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => 'canine', 'ref_min' => '0,5', 'ref_max' => '1,4'])->assertSessionHasNoErrors(); // atualiza
        $this->assertSame(2, $this->inA(fn () => ExamReferenceRange::query()->count()));
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => 'canine', 'ref_min' => '2', 'ref_max' => '1'])->assertSessionHasErrors('ref_min');
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => 'canine', 'ref_min' => 'abc'])->assertSessionHasErrors('ref_min');
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => 'dragao', 'ref_min' => '1'])->assertSessionHasErrors('species');
        $this->get('/configuracoes/exames/'.$type->id.'/parametros')->assertSee('0,5 – 1,4 mg/dL', false)->assertSee('Qualquer espécie');

        $this->post('/configuracoes/exames/'.$type->id.'/parametros', ['name' => 'Fósforo', 'unit' => 'mg/dL'])->assertSessionHasNoErrors();
        $this->post('/configuracoes/exames/'.$type->id.'/parametros', ['name' => 'Fósforo'])->assertSessionHasErrors('name');
        $this->assertTrue($this->inA(fn () => ExamAnalyte::query()->where('name', 'Fósforo')->where('exam_type_id', $type->id)->exists()));
    }

    public function test_valores_com_faixa_da_especie_situacao_e_historico_comparativo(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $creat = $this->analyte('Creatinina');
        $ureia = $this->analyte('Ureia');
        $this->inA(function () use ($creat) {
            ExamReferenceRange::create(['analyte_id' => $creat->id, 'species' => 'canine', 'ref_min' => 0.5, 'ref_max' => 1.5]);
            ExamReferenceRange::create(['analyte_id' => $creat->id, 'species' => 'feline', 'ref_min' => 0.8, 'ref_max' => 1.8]);
        });

        $first = $this->requestRenal(self::THOR);
        $this->get('/exames/'.$first->id)->assertSee('Valores do exame')->assertSee('value="0,5"', false)->assertSee('value="1,5"', false); // faixa do cão
        $this->post('/exames/'.$first->id.'/valores', ['values' => [
            $creat->id => ['value' => '1,2', 'ref_min' => '0,5', 'ref_max' => '1,5'],
            $ureia->id => ['value' => 'não dosado', 'ref_min' => '', 'ref_max' => ''],
        ]])->assertSessionHasNoErrors();
        $first = $this->sys(fn () => $first->fresh());
        $this->assertSame('received', $first->status); // valores registrados = resultado recebido
        $v = $this->sys(fn () => ExamResultValue::query()->where('exam_request_id', $first->id)->get()->keyBy('analyte_id'));
        $this->assertSame(['normal', 'none'], [$v[$creat->id]->flag, $v[$ureia->id]->flag]);
        $this->assertSame('não dosado', $v[$ureia->id]->value_text);
        $this->assertSame('mg/dL', $v[$creat->id]->unit);

        // Faixa do laudo diferente da do catálogo: vale a do resultado.
        $this->post('/exames/'.$first->id.'/valores', ['values' => [$creat->id => ['value' => '1,2', 'ref_min' => '0,5', 'ref_max' => '1,1']]])->assertSessionHasNoErrors();
        $this->assertSame('high', $this->sys(fn () => ExamResultValue::query()->where('exam_request_id', $first->id)->where('analyte_id', $creat->id)->value('flag')));
        $this->post('/exames/'.$first->id.'/revisar', ['interpretation' => 'Revisão fictícia.', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->post('/exames/'.$first->id.'/valores', ['values' => [$creat->id => ['value' => '9']]])->assertSessionHasErrors('values');

        $second = $this->requestRenal(self::THOR);
        $this->post('/exames/'.$second->id.'/valores', ['values' => [$creat->id => ['value' => '2,3', 'ref_min' => '0,5', 'ref_max' => '1,5']]])->assertSessionHasNoErrors();
        $this->get('/exames/'.$second->id)->assertSee('Acima da faixa')->assertSee('1 fora da faixa')->assertSee('Evolução:', false)->assertSee('1,2')->assertSee('2,3');
        $this->get('/exames')->assertSee('fora da faixa');
        $this->get('/pacientes/'.self::THOR)->assertSee('1 fora da faixa');
        // Busca clínica encontra o valor estruturado com a situação.
        $this->get('/busca?q=creatinina')->assertSee('2,3 mg/dL (acima da faixa)', false);

        // Campo vazio apaga o valor digitado (exame ainda aberto); faixa inválida é recusada.
        $this->post('/exames/'.$second->id.'/valores', ['values' => [$creat->id => ['value' => '2,3', 'ref_min' => 'x']]])->assertSessionHasErrors();
        $this->post('/exames/'.$second->id.'/valores', ['values' => [$creat->id => ['value' => '']]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->sys(fn () => ExamResultValue::query()->where('exam_request_id', $second->id)->count()));
    }

    public function test_faixa_sugerida_segue_a_especie_do_paciente(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $creat = $this->analyte('Creatinina');
        $this->inA(fn () => ExamReferenceRange::create(['analyte_id' => $creat->id, 'species' => 'feline', 'ref_min' => 0.8, 'ref_max' => 1.8]));
        $exam = $this->requestRenal(self::MIA);
        $this->get('/exames/'.$exam->id)->assertSee('value="0,8"', false)->assertSee('value="1,8"', false);
        $this->assertSame(1.5, ExamService::parseNumber('1,5'));
        $this->assertSame(1234.5, ExamService::parseNumber('1.234,5'));
        $this->assertSame(19800.0, ExamService::parseNumber('19800'));
        $this->assertNull(ExamService::parseNumber('positivo'));
    }

    public function test_recepcao_nao_registra_valores_nem_configura_parametros(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $creat = $this->analyte('Creatinina');
        $exam = $this->requestRenal(self::THOR);
        $this->post('/sair');
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/exames/'.$exam->id.'/valores', ['values' => [$creat->id => ['value' => '1']]])->assertForbidden();
        $this->post('/configuracoes/exames/parametros/'.$creat->id.'/faixas', ['species' => '*', 'ref_min' => '1'])->assertForbidden();
    }

    #[Group('tenant-isolation')]
    public function test_parametros_faixas_e_valores_isolados_entre_clinicas(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $creatA = $this->analyte('Creatinina');
        $exam = $this->requestRenal(self::THOR);
        $this->post('/exames/'.$exam->id.'/valores', ['values' => [$creatA->id => ['value' => '1,1']]])->assertSessionHasNoErrors();
        $rangeA = $this->inA(fn () => ExamReferenceRange::create(['analyte_id' => $creatA->id, 'species' => '*', 'ref_min' => 0.5, 'ref_max' => 1.5]));
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $creatB = $this->analyte('Creatinina', Seed::TENANT_B);
        $this->assertNotSame($creatA->id, $creatB->id);
        $this->post('/configuracoes/exames/parametros/'.$creatA->id.'/faixas', ['species' => '*', 'ref_min' => '9'])->assertNotFound();
        $this->put('/configuracoes/exames/parametros/'.$creatA->id, ['name' => 'X'])->assertNotFound();
        $this->post('/configuracoes/exames/faixas/'.$rangeA->id.'/remover')->assertNotFound();
        $this->post('/exames/'.$exam->id.'/valores', ['values' => [$creatA->id => ['value' => '5']]])->assertNotFound();
        $this->get('/busca?q=creatinina')->assertDontSee('1,1 mg/dL');
        $this->assertSame('1.1000', $this->sys(fn () => ExamResultValue::query()->where('exam_request_id', $exam->id)->value('value')));
        $this->assertTrue($this->sys(fn () => ExamReferenceRange::query()->whereKey($rangeA->id)->exists()));
    }
}
