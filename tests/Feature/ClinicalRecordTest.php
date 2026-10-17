<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Encounter;
use App\Models\EncounterAddendum;
use App\Models\EncounterDiagnosis;
use App\Models\PatientAlert;
use App\Models\PatientWeight;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Atendimento/prontuário (Fase 1): rascunho, assinatura, bloqueio, adendos, alertas, peso, acesso e isolamento. */
final class ClinicalRecordTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const KIWI = '01K7FV0000000000000PTA0003';

    private const JABU = '01K7FV0000000000000PTB0001';

    private const APPT_THOR = '01K7FV0000000000000APA0001';

    private const ENC_THOR = '01K7FV0000000000000ENA0002'; // finalizado no seed

    private const ENC_JABU = '01K7FV0000000000000ENB0001';

    private function ctx(): TenantContext
    {
        return app(TenantContext::class);
    }

    private function sys(\Closure $fn): mixed
    {
        return $this->ctx()->runAsSystem($fn);
    }

    /** Abre um rascunho para o paciente e devolve o id. */
    private function startFor(string $patientId): string
    {
        $location = (string) $this->post("/pacientes/{$patientId}/atendimentos")->assertRedirect()->headers->get('Location');

        return substr($location, -26); // id do rascunho criado (o redirecionamento aponta para ele)
    }

    /** @param  array<string, mixed>  $over */
    private function draft(array $over = []): array
    {
        return [
            'chief_complaint' => 'Vômito há 2 dias (fictício).', 'history' => 'Comeu ração nova.', 'physical_exam' => 'Abdome sensível.',
            'plan' => 'Dieta leve e retorno em 3 dias.', 'guidance' => 'Oferecer água à vontade.',
            'weight_kg' => '4,350', 'temperature_c' => '39,1', 'heart_rate' => 180, 'respiratory_rate' => 32, 'capillary_refill_s' => '2',
            'mucous_membranes' => 'Normocoradas', 'hydration' => 'Normal', 'body_condition_score' => 5, 'pain_score' => 3,
            'diagnoses' => [
                ['description' => 'Gastroenterite alimentar', 'code' => '', 'status' => 'suspected'],
                ['description' => 'Corpo estranho', 'code' => '', 'status' => 'ruled_out'],
                ['description' => '', 'code' => '', 'status' => 'suspected'],
            ],
            ...$over,
        ];
    }

    public function test_iniciar_atendimento_na_agenda_abre_o_prontuario_ligado_ao_agendamento(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/agenda/'.self::APPT_THOR.'/status', ['to' => 'waiting']);
        $res = $this->post('/agenda/'.self::APPT_THOR.'/status', ['to' => 'in_progress']);
        $enc = $this->sys(fn () => Encounter::query()->where('appointment_id', self::APPT_THOR)->first());
        $res->assertRedirect('/atendimentos/'.$enc?->id);
        $this->assertSame([self::THOR, 'draft'], [$enc?->patient_id, $enc?->status]);
        $this->get('/atendimentos/'.$enc?->id)->assertOk()->assertSee('Rascunho')->assertSee('Finalizar e assinar')
            ->assertSee('Alergia a penicilina (fictícia)'); // alerta em destaque no atendimento
    }

    public function test_rascunho_salva_campos_virgula_decimal_e_diagnosticos_sem_conteudo_na_auditoria(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $id = $this->startFor(self::KIWI);
        $this->put("/atendimentos/{$id}", $this->draft())->assertRedirect("/atendimentos/{$id}")->assertSessionHasNoErrors();

        $e = $this->sys(fn () => Encounter::query()->with('diagnoses')->find($id));
        $this->assertSame(['4.350', '39.1', 180, 'draft'], [$e?->weight_kg, $e?->temperature_c, $e?->heart_rate, $e?->status]);
        $this->assertSame([['Gastroenterite alimentar', 'suspected'], ['Corpo estranho', 'ruled_out']], $e?->diagnoses->map(fn ($d) => [$d->description, $d->status])->all());

        $audit = $this->sys(fn () => AuditEvent::query()->where('action', 'encounter.updated')->where('target_id', $id)->latest('id')->first());
        $this->assertContains('chief_complaint', $audit?->metadata['fields'] ?? []);
        $this->assertStringNotContainsString('Vômito', json_encode($audit?->metadata));

        // Salvar de novo mudando a lista substitui os diagnósticos do rascunho.
        $this->put("/atendimentos/{$id}", $this->draft(['diagnoses' => [['description' => 'Gastrite', 'code' => 'K29', 'status' => 'confirmed']]]))->assertSessionHasNoErrors();
        $this->assertSame(['Gastrite'], $this->sys(fn () => EncounterDiagnosis::query()->where('encounter_id', $id)->pluck('description')->all()));
    }

    public function test_finalizar_exige_confirmacao_conteudo_e_veterinario(): void
    {
        // Carla vira assistente clínica: escreve rascunho, mas não assina.
        $this->sys(fn () => DB::table('memberships')->where('id', $this->membershipId('carla', Seed::TENANT_A))->update(['roles' => json_encode(['clinical_assistant'])]));
        $this->loginInto('carla', Seed::TENANT_A);
        $id = $this->startFor(self::KIWI);
        $this->put("/atendimentos/{$id}", $this->draft())->assertSessionHasNoErrors();
        $this->get("/atendimentos/{$id}")->assertOk()->assertSee('Só o médico-veterinário finaliza e assina')->assertDontSee('Revisei o registro');
        $this->put("/atendimentos/{$id}", $this->draft(['finalize' => '1', 'confirm' => '1']))->assertForbidden();
        $this->post('/sair');

        $this->loginInto('bruno', Seed::TENANT_A);
        $this->put("/atendimentos/{$id}", $this->draft(['finalize' => '1']))->assertSessionHasErrors('confirm');
        $empty = $this->startFor(self::KIWI);
        $this->put("/atendimentos/{$empty}", ['finalize' => '1', 'confirm' => '1'])->assertSessionHasErrors('chief_complaint');
        $this->assertSame('draft', $this->sys(fn () => Encounter::query()->find($id)?->status));
    }

    public function test_finalizado_fica_bloqueado_grava_peso_e_conclui_agendamento(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post('/agenda/'.self::APPT_THOR.'/status', ['to' => 'waiting']);
        $this->post('/agenda/'.self::APPT_THOR.'/status', ['to' => 'in_progress']);
        $id = (string) $this->sys(fn () => Encounter::query()->where('appointment_id', self::APPT_THOR)->value('id'));
        $this->put("/atendimentos/{$id}", $this->draft(['finalize' => '1', 'confirm' => '1']))->assertRedirect("/atendimentos/{$id}")->assertSessionHasNoErrors();

        $e = $this->sys(fn () => Encounter::query()->find($id));
        $this->assertSame('finalized', $e?->status);
        $this->assertNotNull($e?->finalized_at);
        $this->assertSame($this->membershipId('bruno', Seed::TENANT_A), $e?->signer_membership_id);
        $this->assertSame('completed', $this->sys(fn () => Appointment::query()->find(self::APPT_THOR)?->status));
        $this->assertSame('4.350', $this->sys(fn () => PatientWeight::query()->where('encounter_id', $id)->value('weight_kg')));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'encounter.finalized')->where('target_id', $id)->exists()));

        // Tela: leitura + adendos; nada de formulário de edição.
        $this->get("/atendimentos/{$id}")->assertOk()->assertSee('Finalizado e assinado por')->assertSee('Novo adendo')->assertDontSee('Salvar rascunho');
        // HTTP: alteração recusada.
        $this->put("/atendimentos/{$id}", $this->draft(['chief_complaint' => 'Reescrita indevida']))->assertSessionHasErrors('record');
        $this->assertSame('Vômito há 2 dias (fictício).', $this->sys(fn () => Encounter::query()->find($id)?->chief_complaint));
    }

    public function test_bloqueio_tambem_no_model_mesmo_em_modo_sistema(): void
    {
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            $e = Encounter::query()->findOrFail(self::ENC_THOR);
            $e->chief_complaint = 'Alteração direta';
            try {
                $e->save();
                $this->fail('Encounter finalizado foi alterado.');
            } catch (ClinicalRecordLocked) {
                $this->assertTrue(true);
            }
            $this->expectException(ClinicalRecordLocked::class);
            EncounterDiagnosis::create(['encounter_id' => self::ENC_THOR, 'patient_id' => self::THOR, 'description' => 'Inserido depois', 'status' => 'confirmed']);
        });
    }

    public function test_adendos_so_em_finalizado_e_somente_inclusao(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $draft = $this->startFor(self::KIWI);
        $this->post("/atendimentos/{$draft}/adendos", ['body' => 'Não deveria'])->assertSessionHasErrors('body');
        $this->post('/atendimentos/'.self::ENC_THOR.'/adendos', ['body' => 'Correção: peso aferido em balança recalibrada.'])->assertSessionHasNoErrors();
        $this->get('/atendimentos/'.self::ENC_THOR)->assertSee('Correção: peso aferido em balança recalibrada.')->assertSee('Bruno Veterinário (fictício)');

        $this->expectException(ClinicalRecordLocked::class);
        $this->ctx()->runForTenant(Seed::TENANT_A, function () {
            $ad = EncounterAddendum::query()->where('encounter_id', self::ENC_THOR)->firstOrFail();
            $ad->body = 'editado';
            $ad->save();
        });
    }

    public function test_recepcao_e_financeiro_nao_veem_nada_clinico(): void
    {
        foreach (['carla', 'davi'] as $who) {
            $this->loginInto($who, Seed::TENANT_A);
            $this->get('/pacientes/'.self::THOR)->assertOk()->assertDontSee('Prontuário</h2>', false)
                ->assertDontSee('Alergia a penicilina')->assertDontSee('Dermatite atópica')->assertDontSee('28,15')
                ->assertSee('visíveis só para a equipe clínica');
            $this->get('/atendimentos/'.self::ENC_THOR)->assertForbidden();
            $this->post('/pacientes/'.self::THOR.'/atendimentos')->assertForbidden();
            $this->post('/pacientes/'.self::THOR.'/alertas', ['kind' => 'allergy', 'severity' => 'high', 'description' => 'x'])->assertForbidden();
            $this->post('/sair');
        }
        $this->assertFalse($this->sys(fn () => AuditEvent::query()->whereIn('action', ['patient.clinical_viewed', 'encounter.viewed'])->exists()));
    }

    public function test_acesso_ao_prontuario_e_auditado_e_ficha_mostra_historico_e_peso(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertSee('Prontuário')->assertSee('Dermatite atópica')
            ->assertSee('Confirmado')->assertSee('Suspeita')->assertSee('28,15')->assertSee('Evolução do peso', false);
        $this->get('/atendimentos/'.self::ENC_THOR)->assertOk();
        $actor = DatabaseSeeder::USERS['bruno']['id'];
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'patient.clinical_viewed')->where('target_id', self::THOR)->where('actor_user_id', $actor)->exists()));
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'encounter.viewed')->where('target_id', self::ENC_THOR)->where('actor_user_id', $actor)->exists()));
    }

    public function test_alertas_adicionar_destacar_e_resolver(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->from('/pacientes/'.self::KIWI)->post('/pacientes/'.self::KIWI.'/alertas', ['kind' => 'behavior', 'severity' => 'medium', 'description' => 'Bica ao ser contida'])
            ->assertRedirect('/pacientes/'.self::KIWI);
        $this->get('/pacientes/'.self::KIWI)->assertSee('1 alerta clínico ativo')->assertSee('Bica ao ser contida');
        $alert = (string) $this->sys(fn () => PatientAlert::query()->where('patient_id', self::KIWI)->value('id'));
        $this->post("/alertas/{$alert}/resolver")->assertRedirect();
        $this->get('/pacientes/'.self::KIWI)->assertDontSee('alerta clínico ativo')->assertSee('Nenhum alerta ativo');
        $this->assertNotNull($this->sys(fn () => PatientAlert::query()->find($alert)?->resolved_at));
    }

    public function test_validacoes_dos_sinais_vitais_e_diagnosticos(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $id = $this->startFor(self::KIWI);
        $this->put("/atendimentos/{$id}", $this->draft(['temperature_c' => '55']))->assertSessionHasErrors('temperature_c');
        $this->put("/atendimentos/{$id}", $this->draft(['weight_kg' => 'abc']))->assertSessionHasErrors('weight_kg');
        $this->put("/atendimentos/{$id}", $this->draft(['mucous_membranes' => 'Roxas']))->assertSessionHasErrors('mucous_membranes');
        $this->put("/atendimentos/{$id}", $this->draft(['diagnoses' => [['description' => '', 'code' => 'K29', 'status' => 'confirmed']]]))->assertSessionHasErrors('diagnoses.0.description');
        $this->put("/atendimentos/{$id}", $this->draft(['diagnoses' => [['description' => 'X', 'code' => '', 'status' => 'talvez']]]))->assertSessionHasErrors('diagnoses.0.status');
    }

    // ---------------- Isolamento entre clínicas ----------------

    #[Group('tenant-isolation')]
    public function test_isolamento_prontuario_de_outra_clinica_responde_404(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/atendimentos/'.self::ENC_THOR)->assertNotFound();
        $this->put('/atendimentos/'.self::ENC_THOR, $this->draft())->assertNotFound();
        $this->post('/atendimentos/'.self::ENC_THOR.'/adendos', ['body' => 'invasão'])->assertNotFound();
        $this->post('/pacientes/'.self::THOR.'/atendimentos')->assertNotFound();
        $this->post('/pacientes/'.self::THOR.'/alertas', ['kind' => 'allergy', 'severity' => 'high', 'description' => 'x'])->assertNotFound();
        $alertA = (string) $this->sys(fn () => PatientAlert::query()->where('patient_id', self::THOR)->value('id'));
        $this->post("/alertas/{$alertA}/resolver")->assertNotFound();
        $jabu = $this->get('/pacientes/'.self::JABU)->assertOk();
        foreach (['Suspeita de doença renal'] as $t) {
            $jabu->assertSee($t);
        }
        foreach (['Dermatite', 'Alergia a penicilina (fictícia)'] as $t) {
            $jabu->assertDontSee($t);
        }
        $this->get('/atendimentos/'.self::ENC_JABU)->assertOk();
        $this->assertNull($this->sys(fn () => PatientAlert::query()->find($alertA)?->resolved_at));
        $this->assertSame(0, $this->sys(fn () => EncounterAddendum::query()->where('encounter_id', self::ENC_THOR)->count()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_eva_ve_prontuario_so_da_clinica_ativa(): void
    {
        $this->loginInto('eva', Seed::TENANT_B);
        $this->get('/atendimentos/'.self::ENC_JABU)->assertOk();
        $this->get('/atendimentos/'.self::ENC_THOR)->assertNotFound();
        $this->post('/contexto', ['membership_id' => $this->membershipId('eva', Seed::TENANT_A)]);
        $this->get('/atendimentos/'.self::ENC_THOR)->assertOk();
        $this->get('/atendimentos/'.self::ENC_JABU)->assertNotFound();
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_registro_clinico_cruzado(): void
    {
        $this->expectException(QueryException::class);
        $this->sys(fn () => DB::table('encounter_diagnoses')->insert([
            'id' => '01K7FV0000000000000DXX0001', 'tenant_id' => Seed::TENANT_B, 'encounter_id' => self::ENC_THOR, // atendimento da A
            'patient_id' => self::JABU, 'description' => 'cruzado', 'status' => 'confirmed', 'position' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
