<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ClinicalCase;
use App\Models\ClinicalCasePatient;
use App\Models\Encounter;
use App\Services\ClinicalCaseService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Casos científicos: série a partir da busca, relato a partir do atendimento, exportação anonimizada, permissões e isolamento. */
final class ClinicalCaseTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const ENC_SUSP = '01K7FV0000000000000ENA0001'; // Thor: dermatite atópica suspeita

    private const ENC_CONF = '01K7FV0000000000000ENA0002'; // Thor: dermatite atópica confirmada

    private const ENC_B = '01K7FV0000000000000ENB0001'; // clínica B

    private const CASE = ['title' => 'Dermatite atópica em cães', 'question' => 'Qual a resposta ao tratamento em 30 dias?', 'specialty' => 'Dermatologia'];

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function series(string $query = 'diagnostico=dermatite&situacao=confirmed'): ClinicalCase
    {
        $this->post('/casos?'.$query, self::CASE)->assertRedirect()->assertSessionHasNoErrors();

        return $this->sys(fn () => ClinicalCase::query()->with('entries')->latest('created_at')->latest('id')->firstOrFail());
    }

    public function test_serie_a_partir_da_busca_grava_criterios_pseudonimo_e_atendimento_de_referencia(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes?diagnostico=dermatite&situacao=confirmed')->assertSee('Criar série de casos');
        $this->get('/casos/novo?diagnostico=dermatite&situacao=confirmed')->assertOk()->assertSee('Criar série com 1 caso')->assertSee('Somente confirmado');
        $this->post('/casos?diagnostico=dermatite', ['title' => '', 'question' => ''])->assertSessionHasErrors(['title', 'question']);
        $this->post('/casos', self::CASE)->assertNotFound(); // série sem nenhum filtro = clínica inteira: recusada
        $this->get('/casos/novo')->assertNotFound();

        $case = $this->series();
        $this->assertSame(['series', 'Dermatite atópica em cães'], [$case->kind, $case->title]);
        $this->assertEquals(['diagnostico' => 'dermatite', 'situacao' => 'confirmed'], $case->criteria);
        $this->assertCount(1, $case->entries);
        $this->assertSame([self::THOR, self::ENC_CONF, 'Caso 01', 'pending', 'unknown'], [$case->entries[0]->patient_id, $case->entries[0]->encounter_id, $case->entries[0]->label, $case->entries[0]->consent, $case->entries[0]->outcome]);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'clinical_case.created')->where('target_id', $case->id)->exists()));

        $this->get("/casos/{$case->id}")->assertOk()->assertSee('Caso 01')->assertSee('Thor')->assertSee('Somente confirmado')
            ->assertSee('Consentimento: Pendente');
        $this->get('/casos')->assertOk()->assertSee('Dermatite atópica em cães')->assertSee('1 caso');
    }

    public function test_relato_a_partir_de_atendimento_finalizado(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/atendimentos/'.self::ENC_SUSP)->assertSee('Criar relato de caso');
        $this->get('/casos/novo?atendimento='.self::ENC_SUSP)->assertOk()->assertSee('Atendimento de origem')->assertSee('Suspeita: Dermatite atópica');
        $this->post('/casos?atendimento='.self::ENC_SUSP, ['title' => 'Relato: prurido em cão', 'question' => 'Descrever o caso.'])->assertRedirect();
        $case = $this->sys(fn () => ClinicalCase::query()->with('entries')->where('kind', 'report')->firstOrFail());
        $this->assertSame(self::ENC_SUSP, $case->entries[0]->encounter_id);

        $draft = $this->sys(fn () => app(TenantContext::class)->runForTenant(Seed::TENANT_A, fn () => Encounter::create(['unit_id' => '01K7FV000000000000000TNA01', 'patient_id' => self::THOR, 'status' => 'draft', 'started_at' => now()])));
        $this->get("/casos/novo?atendimento={$draft->id}")->assertNotFound(); // rascunho não vira caso
    }

    public function test_exportacao_anonimizada_so_com_consentimento(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $case = $this->series();
        $entry = $case->entries[0];

        // Sem consentimento: nenhuma linha exportada.
        $csv = (string) $this->get("/casos/{$case->id}/exportar.csv")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertSame(1, substr_count(trim($csv), "\n") + 1); // só cabeçalho
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'clinical_case.exported')->get()->contains(fn ($e) => ($e->metadata['excluded_without_consent'] ?? null) === 1)));

        $this->put("/casos/{$case->id}/pacientes/{$entry->id}", [
            'outcome' => 'improved', 'consent' => 'granted', 'consent_on' => now()->format('Y-m-d'),
            'notes' => 'Thor melhorou; Marina (tutora) ligou do 11 98765-4321 e escreveu para marina@aurora.exemplo.test',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'clinical_case.consent_recorded')->exists()));

        $csv = (string) $this->get("/casos/{$case->id}/exportar.csv")->getContent();
        $month = $this->sys(fn () => Encounter::query()->findOrFail(self::ENC_CONF))->started_at->timezone('America/Sao_Paulo');
        $this->assertStringContainsString('"Caso 01";Cão;SRD;Macho;Castrado(a)', $csv);
        $this->assertStringContainsString($month->format('m/Y'), $csv);
        $this->assertStringContainsString('Dermatite atópica (confirmado)', $csv);
        $this->assertStringContainsString('Melhora', $csv);
        $this->assertStringContainsString('[nome omitido] melhorou; [nome omitido] (tutora) ligou do [número omitido] e escreveu para [e-mail omitido]', $csv);
        foreach (['Thor', 'Marina', 'Souza', '98765', '985112000000001', 'aurora.exemplo', $month->format('d/m/Y'), self::THOR, self::ENC_CONF] as $secret) {
            $this->assertStringNotContainsString($secret, $csv, "Exportação vazou: {$secret}");
        }

        $pdf = $this->get("/casos/{$case->id}/exportar.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
    }

    public function test_csv_neutraliza_formula_e_redacao_de_nomes(): void
    {
        $this->assertSame('[nome omitido] e Thorvaldo', ClinicalCaseService::redact('Thor e Thorvaldo', ['Thor']));
        $this->assertSame('CPF [número omitido]', ClinicalCaseService::redact('CPF 529.982.247-25', []));

        $this->loginInto('bruno', Seed::TENANT_A);
        $case = $this->series();
        $this->put("/casos/{$case->id}/pacientes/{$case->entries[0]->id}", ['outcome' => 'stable', 'consent' => 'granted', 'notes' => '=HYPERLINK("http://x")'])->assertSessionHasNoErrors();
        $this->assertStringContainsString(";\"'=HYPERLINK(\"\"http://x\"\")\"", (string) $this->get("/casos/{$case->id}/exportar.csv")->getContent());
    }

    public function test_caso_encerrado_fica_somente_leitura_e_remover_nao_toca_o_prontuario(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $case = $this->series('especie=canine');
        $entry = $case->entries[0];
        $this->put("/casos/{$case->id}", [...self::CASE, 'summary' => 'Discussão.', 'status' => 'closed'])->assertSessionHasNoErrors();
        $this->from("/casos/{$case->id}")->put("/casos/{$case->id}/pacientes/{$entry->id}", ['outcome' => 'resolved', 'consent' => 'granted'])->assertSessionHasErrors('record');
        $this->get("/casos/{$case->id}")->assertDontSee('Salvar Caso 01');

        $this->put("/casos/{$case->id}", [...self::CASE, 'status' => 'open']);
        $this->post("/casos/{$case->id}/pacientes/{$entry->id}/remover")->assertSessionHasNoErrors();
        $this->assertFalse($this->sys(fn () => ClinicalCasePatient::query()->whereKey($entry->id)->exists()));
        $this->assertTrue($this->sys(fn () => Encounter::query()->whereKey(self::ENC_CONF)->exists()));
    }

    public function test_somente_veterinario_e_titular(): void
    {
        foreach (['carla', 'davi'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get('/casos')->assertForbidden();
            $this->post('/casos?especie=canine', self::CASE)->assertForbidden();
            $this->post('/sair');
        }
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes?especie=canine')->assertDontSee('Criar série de casos');
        $this->post('/sair');
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/casos')->assertOk()->assertSee('Nenhum caso científico ainda');
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_casos_entre_clinicas(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $case = $this->series();
        $entry = $case->entries[0];
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/casos')->assertOk()->assertDontSee('Dermatite atópica em cães');
        $this->get("/casos/{$case->id}")->assertNotFound();
        $this->get("/casos/{$case->id}/exportar.csv")->assertNotFound();
        $this->put("/casos/{$case->id}", [...self::CASE, 'status' => 'closed'])->assertNotFound();
        $this->put("/casos/{$case->id}/pacientes/{$entry->id}", ['outcome' => 'resolved', 'consent' => 'granted'])->assertNotFound();
        $this->post("/casos/{$case->id}/pacientes/{$entry->id}/remover")->assertNotFound();
        $this->get('/casos/novo?atendimento='.self::ENC_CONF)->assertNotFound();
        // Série da clínica B com o mesmo filtro não traz pacientes da A.
        $this->post('/casos?diagnostico=dermatite', self::CASE)->assertSessionHasErrors('record');
        $this->assertFalse($this->sys(fn () => ClinicalCase::query()->where('tenant_id', Seed::TENANT_B)->exists()));
        $this->assertSame('open', $this->sys(fn () => ClinicalCase::query()->findOrFail($case->id)->status));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_paciente_de_outra_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $mine = $this->series('diagnostico=renal');
        $this->assertSame(self::ENC_B, $mine->entries[0]->encounter_id);
        $this->expectException(QueryException::class);
        $this->sys(fn () => DB::table('clinical_case_patients')->insert([
            'id' => '01K7FV0000000000000CCX0001', 'tenant_id' => Seed::TENANT_B, 'case_id' => $mine->id, 'patient_id' => self::THOR, // paciente da A
            'label' => 'Caso 99', 'added_by' => Seed::USERS['diana']['id'], 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
