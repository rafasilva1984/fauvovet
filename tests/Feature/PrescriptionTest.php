<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Models\AuditEvent;
use App\Models\IssuedDocument;
use App\Models\Medication;
use App\Models\PrescriptionItem;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Receita estruturada: catálogo, cálculo pelo peso, avisos (alergia/faixa/controle), emissão, imutabilidade e isolamento A/B. */
final class PrescriptionTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001'; // cão, 28,150 kg, alergia a penicilina (seed)

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

    /** @param array<string, mixed> $over */
    private function medication(string $tenant, string $name, array $over = []): Medication
    {
        return app(TenantContext::class)->runForTenant($tenant, fn () => Medication::create([
            'name' => $name, 'form' => 'tablet', 'concentration_mg' => 250, 'dose_mg_kg' => 12.5, 'dose_min_mg_kg' => 10, 'dose_max_mg_kg' => 20,
            'route' => 'Oral', 'frequency' => 'A cada 12 horas', 'duration_days' => 7, 'species' => [], 'controlled' => false, 'active' => true, ...$over,
        ]));
    }

    /** @param list<array<string, mixed>> $rows */
    private function submit(array $rows, string $action = 'issue', array $over = [], string $patient = self::THOR): TestResponse
    {
        return $this->post("/pacientes/{$patient}/receitas", ['action' => $action, 'rows' => $rows, ...$over]);
    }

    public function test_calcular_e_revisar_preenche_dose_pelo_peso_sem_gravar(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertOk()->assertSee('28,15')->assertSee('Cefalexina fictícia')
            ->assertSee('Alergia a penicilina (fictícia)');

        $this->submit([['medication_id' => $med->id]], 'preview')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
        // 12,5 mg/kg × 28,15 kg = 351,875 mg → 1 ½ comprimido (375 mg) — 13,32 mg/kg efetivos (dentro da faixa 10–20).
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertOk()
            ->assertSee('1 ½ comprimido (375 mg)')->assertSee('dose efetiva 13,32 mg/kg')
            ->assertSee('VIA ORAL')->assertSee('a cada 12 horas, por 7 dias.')->assertDontSee('Confira antes de emitir');
    }

    public function test_emitir_grava_documento_itens_pdf_e_auditoria(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $res = $this->submit([
            ['medication_id' => $med->id, 'instructions' => 'Dar junto com a refeição.'],
            ['name' => 'Xampu fictício de clorexidina', 'dose_text' => 'Banho', 'route' => 'Tópica', 'frequency' => 'Uma vez por semana', 'continuous' => '1'],
        ], 'issue', ['encounter_id' => self::ENC_THOR, 'guidance' => 'Retorno em 10 dias.']);
        $doc = $this->sys(fn () => IssuedDocument::query()->with('prescriptionItems')->firstOrFail());
        $res->assertRedirect("/documentos/{$doc->id}");

        $this->assertSame(['prescription', self::ENC_THOR, null], [$doc->category, $doc->encounter_id, $doc->template_id]);
        $this->assertStringContainsString("VIA ORAL\n1. Cefalexina fictícia — comprimido 250 mg\n   1 ½ comprimido (375 mg), a cada 12 horas, por 7 dias.\n   Dar junto com a refeição.", $doc->body);
        $this->assertStringContainsString("VIA TÓPICA\n2. Xampu fictício de clorexidina\n   Banho, uma vez por semana, uso contínuo.", $doc->body);
        $this->assertStringContainsString('Orientações: Retorno em 10 dias.', $doc->body);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($doc->pdf_path));

        [$a, $b] = $doc->prescriptionItems->all();
        $this->assertSame([$med->id, '28.150', '12.5000', '375.0000', '1.5000', 7], [$a->medication_id, $a->weight_kg, $a->dose_mg_kg, $a->dose_mg, $a->quantity, $a->duration_days]);
        $this->assertSame(now('America/Sao_Paulo')->addDays(6)->toDateString(), $a->ends_on?->toDateString());
        $this->assertSame([null, null, true, null], [$b->medication_id, $b->dose_mg_kg, $b->continuous, $b->ends_on]);
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'prescription.issued')->where('target_id', $doc->id)->exists()));

        $this->get("/documentos/{$doc->id}")->assertOk()->assertSee('Medicamentos prescritos')->assertSee('12,5 mg/kg × 28,15 kg');
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertSee('Medicamentos em uso')->assertSee('Xampu fictício de clorexidina');
        // Texto da receita entra na busca clínica.
        $this->get('/busca?q=cefalexina')->assertOk()->assertSee('Thor');
    }

    public function test_dose_digitada_prevalece_e_nao_grava_calculo(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([['medication_id' => $med->id, 'dose_text' => '1 comprimido']])->assertRedirect();
        $item = $this->sys(fn () => PrescriptionItem::query()->firstOrFail());
        $this->assertSame(['1 comprimido', null, null, null], [$item->dose_text, $item->dose_mg_kg, $item->dose_mg, $item->weight_kg]);
    }

    public function test_dose_preenchida_pelo_sistema_e_recalculada_quando_o_peso_muda(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $auto = '1 ½ comprimido (375 mg)';
        $this->submit([['medication_id' => $med->id, 'dose_text' => $auto, 'dose_auto' => $auto]], 'issue', ['weight_kg' => '40'])->assertRedirect();
        // 12,5 × 40 = 500 mg → 2 comprimidos.
        $this->assertSame('2 comprimidos (500 mg)', $this->sys(fn () => PrescriptionItem::query()->value('dose_text')));
    }

    public function test_alergia_da_classe_exige_confirmacao(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Amoxicilina fictícia', ['drug_class' => 'Penicilinas (betalactâmicos)']);
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([['medication_id' => $med->id]])->assertSessionHasErrors('confirm_warnings');
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertSee('o paciente tem alergia registrada')->assertSee('Revisei os avisos');
        $this->submit([['medication_id' => $med->id]], 'issue', ['confirm_warnings' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->sys(fn () => IssuedDocument::query()->count()));
    }

    public function test_dose_fora_da_faixa_e_controle_especial_geram_aviso(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Tramadol fictício', ['concentration_mg' => 50, 'dose_mg_kg' => 2, 'dose_min_mg_kg' => 1, 'dose_max_mg_kg' => 4, 'controlled' => true]);
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([['medication_id' => $med->id, 'dose_mg_kg' => '6']], 'preview');
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertSee('fora da faixa cadastrada pela clínica (1 a 4 mg/kg)')
            ->assertSee('controle especial (duas vias');
        $this->submit([['medication_id' => $med->id]])->assertSessionHasErrors('confirm_warnings'); // controlado sempre pede confirmação
    }

    public function test_validacao_linha_sem_dose_via_ou_peso(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia', ['route' => null, 'frequency' => null]);
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([])->assertSessionHasErrors('rows');
        $this->submit([['name' => 'Fictício sem dose']])->assertSessionHasErrors(['rows.0.dose_text', 'rows.0.route', 'rows.0.frequency']);
        $this->sys(fn () => DB::table('patient_weights')->delete() + DB::table('encounters')->update(['weight_kg' => null]));
        $this->submit([['medication_id' => $med->id, 'route' => 'Oral', 'frequency' => 'Dose única']])->assertSessionHasErrors('rows.0.dose_text');
        $this->assertSame(0, $this->sys(fn () => IssuedDocument::query()->count()));
    }

    public function test_catalogo_valida_faixa_e_e_restrito(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/configuracoes/medicamentos')->assertOk()->assertSee('Nenhum medicamento cadastrado');
        $base = ['name' => 'Meloxicam fictício', 'form' => 'suspension', 'concentration_mg' => '0,5', 'dose_mg_kg' => '0,1', 'dose_min_mg_kg' => '0,05', 'dose_max_mg_kg' => '0,2', 'route' => 'Oral'];
        $this->post('/configuracoes/medicamentos', [...$base, 'dose_max_mg_kg' => '0,01'])->assertSessionHasErrors('dose_max_mg_kg');
        $this->post('/configuracoes/medicamentos', [...$base, 'dose_mg_kg' => '1'])->assertSessionHasErrors('dose_mg_kg');
        $this->post('/configuracoes/medicamentos', $base)->assertRedirect('/configuracoes/medicamentos')->assertSessionHasNoErrors();
        $this->get('/configuracoes/medicamentos')->assertSee('suspensão oral 0,5 mg/mL')->assertSee('faixa 0,05 a 0,2 mg/kg');
        $this->post('/configuracoes/medicamentos', $base)->assertSessionHasErrors('name');
        $this->post('/sair');

        $this->loginInto('carla', Seed::TENANT_A); // recepção
        $this->get('/configuracoes/medicamentos')->assertForbidden();
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertForbidden();
    }

    public function test_assistente_clinico_nao_emite_receita(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->sys(fn () => DB::table('memberships')->where('id', $this->membershipId('carla', Seed::TENANT_A))->update(['roles' => json_encode(['clinical_assistant'])]));
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertForbidden();
        $this->submit([['medication_id' => $med->id]])->assertForbidden();
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertDontSee('Nova receita');
    }

    public function test_itens_sao_imutaveis_e_receita_anulada_sai_de_em_uso(): void
    {
        $med = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([['medication_id' => $med->id]]);
        $doc = $this->sys(fn () => IssuedDocument::query()->firstOrFail());
        $this->get('/pacientes/'.self::THOR)->assertSee('Cefalexina fictícia');
        $this->post("/documentos/{$doc->id}/anular", ['reason' => 'Dose corrigida'])->assertRedirect();
        $this->get('/pacientes/'.self::THOR)->assertSee('Nenhum medicamento em uso');

        app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () {
            $item = PrescriptionItem::query()->firstOrFail();
            $item->dose_text = 'reescrito';
            try {
                $item->save();
                $this->fail('Item de receita foi alterado.');
            } catch (ClinicalRecordLocked) {
                $this->assertTrue(true);
            }
            $this->expectException(ClinicalRecordLocked::class);
            PrescriptionItem::query()->firstOrFail()->delete();
        });
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_catalogo_receita_e_atendimento_de_outra_clinica(): void
    {
        $medA = $this->medication(Seed::TENANT_A, 'Cefalexina fictícia');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->submit([['medication_id' => $medA->id]]);
        $this->post('/sair');

        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/configuracoes/medicamentos')->assertOk()->assertDontSee('Cefalexina fictícia');
        $this->get("/configuracoes/medicamentos/{$medA->id}/editar")->assertNotFound();
        $this->put("/configuracoes/medicamentos/{$medA->id}", ['name' => 'Invasão', 'form' => 'tablet'])->assertNotFound();
        $this->get('/pacientes/'.self::THOR.'/receitas/nova')->assertNotFound();
        $this->submit([['medication_id' => $medA->id]], 'issue', [], self::THOR)->assertNotFound();
        // Paciente próprio com medicamento ou atendimento de A (IDs válidos de outra clínica) → recusado.
        $this->submit([['medication_id' => $medA->id]], 'issue', [], self::JABU)->assertNotFound();
        $this->submit([['name' => 'Fictício', 'dose_text' => '1 mL', 'route' => 'Oral', 'frequency' => 'Dose única']], 'issue', ['encounter_id' => self::ENC_THOR], self::JABU)->assertNotFound();
        $this->get('/pacientes/'.self::JABU.'/receitas/nova')->assertOk()->assertDontSee('Cefalexina fictícia');

        $this->assertSame(1, $this->sys(fn () => IssuedDocument::query()->count()));
        $this->assertSame([Seed::TENANT_A], $this->sys(fn () => PrescriptionItem::query()->distinct()->pluck('tenant_id')->all()));
        $this->assertSame('Cefalexina fictícia', $this->sys(fn () => Medication::query()->find($medA->id)?->name));
    }
}
