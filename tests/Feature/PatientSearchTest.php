<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Encounter;
use App\Models\EncounterDiagnosis;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Busca avançada de pacientes: filtros de cadastro, filtros clínicos com evidência, permissões e isolamento A/B. */
final class PatientSearchTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001'; // cão SRD, macho, castrado, nascido em 2019; dermatite atópica

    private const MIA = '01K7FV0000000000000PTA0002'; // gata persa, fêmea, nascida em 2021

    private const KIWI = '01K7FV0000000000000PTA0003'; // ave, sem data de nascimento

    private const JABU = '01K7FV0000000000000PTB0001'; // clínica B; suspeita de doença renal

    /** @param list<string> $in @param list<string> $out */
    private function assertPatients(string $url, array $in, array $out): void
    {
        $res = $this->get($url)->assertOk();
        foreach ($in as $id) {
            $res->assertSee('/pacientes/'.$id, false);
        }
        foreach ($out as $id) {
            $res->assertDontSee('/pacientes/'.$id, false);
        }
    }

    public function test_filtros_de_cadastro_raca_sexo_castracao_e_idade(): void
    {
        $this->loginInto('carla', Seed::TENANT_A); // recepção também usa os filtros de cadastro
        $this->assertPatients('/pacientes?raca=pers', [self::MIA], [self::THOR, self::KIWI]);
        $this->assertPatients('/pacientes?sexo=male', [self::THOR], [self::MIA, self::KIWI]);
        $this->assertPatients('/pacientes?castrado=unknown', [self::KIWI], [self::THOR, self::MIA]);
        $thorAge = now()->diffInYears('2019-03-10', true);
        $this->assertPatients('/pacientes?idade_min='.(int) floor($thorAge), [self::THOR], [self::MIA, self::KIWI]);
        $this->assertPatients('/pacientes?idade_max='.((int) floor($thorAge) - 1), [self::MIA], [self::THOR, self::KIWI]); // sem nascimento fica fora
        $this->assertPatients('/pacientes?especie=canine&sexo=female', [], [self::THOR, self::MIA, self::KIWI]);
        $this->get('/pacientes?sexo=dragao&idade_min=abc')->assertOk()->assertSee('/pacientes/'.self::KIWI, false); // inválido = filtro desligado
        $this->get('/pacientes?raca=persa')->assertSee('Raça: persa')->assertSee('Filtros avançados');
    }

    public function test_diagnostico_com_situacao_evidencia_e_auditoria(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        // Sem acento e em minúsculas encontra "Dermatite atópica"; mostra a situação e o atendimento de origem.
        $this->get('/pacientes?diagnostico=dermatite+atopica')->assertOk()
            ->assertSee('/pacientes/'.self::THOR, false)->assertDontSee('/pacientes/'.self::MIA, false)
            ->assertSee('Confirmado')->assertSee('Suspeita')
            ->assertSee('/atendimentos/01K7FV0000000000000ENA0002', false)->assertSee('/atendimentos/01K7FV0000000000000ENA0001', false);
        $this->get('/pacientes?diagnostico=dermatite&situacao=confirmed')->assertOk()
            ->assertSee('/atendimentos/01K7FV0000000000000ENA0002', false)->assertDontSee('/atendimentos/01K7FV0000000000000ENA0001', false);
        $this->assertPatients('/pacientes?diagnostico=dermatite&situacao=ruled_out', [], [self::THOR]);
        $this->assertPatients('/pacientes?situacao=confirmed&especie=canine', [self::THOR], [self::MIA]);
        $this->assertPatients('/pacientes?diagnostico=dermatite&especie=feline', [], [self::THOR]);

        $year = (int) now()->subDays(30)->timezone('America/Sao_Paulo')->year;
        $this->assertPatients("/pacientes?ano={$year}", [self::THOR], [self::MIA, self::KIWI]);
        $this->assertPatients('/pacientes?ano=1995', [], [self::THOR]);

        $event = app(TenantContext::class)->runAsSystem(fn () => AuditEvent::query()->where('action', 'patient.clinical_search')->latest('id')->first());
        $this->assertNotNull($event);
        $this->assertSame(['ano'], $event->metadata['filters'] ?? null);
        $this->assertStringNotContainsString('dermatite', (string) json_encode(app(TenantContext::class)->runAsSystem(fn () => AuditEvent::query()->where('action', 'patient.clinical_search')->pluck('metadata'))));
    }

    public function test_rascunho_nao_entra_na_busca_clinica(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () {
            $e = Encounter::create(['unit_id' => '01K7FV000000000000000TNA01', 'patient_id' => self::MIA, 'status' => 'draft', 'started_at' => now()]);
            EncounterDiagnosis::create(['encounter_id' => $e->id, 'patient_id' => self::MIA, 'description' => 'Dermatite por pulgas', 'status' => 'confirmed']);
        });
        $this->assertPatients('/pacientes?diagnostico=dermatite', [self::THOR], [self::MIA]);
    }

    public function test_recepcao_nao_usa_nem_ve_filtros_clinicos(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes')->assertOk()->assertDontSee('Diagnóstico ou código')->assertDontSee('Ano de atendimento');
        // Filtro clínico na URL é ignorado no servidor: não restringe nem mostra diagnósticos.
        $this->get('/pacientes?diagnostico=dermatite&situacao=confirmed&ano=2026')->assertOk()
            ->assertSee('/pacientes/'.self::MIA, false)->assertDontSee('Dermatite')->assertDontSee('/atendimentos/', false);
        $this->assertFalse(app(TenantContext::class)->runAsSystem(fn () => AuditEvent::query()->where('action', 'patient.clinical_search')->exists()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_busca_clinica_entre_clinicas(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/pacientes?diagnostico=dermatite')->assertOk()->assertDontSee('/pacientes/'.self::THOR, false)->assertDontSee('Dermatite');
        $this->assertPatients('/pacientes?diagnostico=renal', [self::JABU], []);
        $this->assertPatients('/pacientes?raca=persa', [], [self::MIA]);
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes?diagnostico=renal')->assertOk()->assertDontSee('/pacientes/'.self::JABU, false)->assertDontSee('Suspeita de doença renal');
    }
}
