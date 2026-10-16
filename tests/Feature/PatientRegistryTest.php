<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Guardian;
use App\Models\Patient;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Responsáveis e pacientes (Fase 1): cadastro rápido, busca, vínculos, permissões e isolamento A/B. */
final class PatientRegistryTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const MIA = '01K7FV0000000000000PTA0002';

    private const JABU = '01K7FV0000000000000PTB0001';

    private const MARINA = '01K7FV0000000000000GRDA001';

    private const RAFAEL = '01K7FV0000000000000GRDA002';

    private const HELENA = '01K7FV0000000000000GRDB001';

    private function ctx(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function newPatient(array $overrides = []): array
    {
        return [
            'name' => 'Bolinha', 'species' => 'canine', 'sex' => 'male', 'neutered' => 'unknown', 'relationship' => 'primary',
            'g_name' => 'Tutor Novo (fictício)', 'g_phone' => '(21) 99999-0000', 'g_person_type' => 'PF',
            ...$overrides,
        ];
    }

    private function patient(string $name): ?Patient
    {
        return $this->ctx()->runAsSystem(fn () => Patient::query()->where('name', $name)->latest('created_at')->first());
    }

    public function test_recepcao_lista_e_busca_pacientes_da_propria_clinica(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/pacientes')->assertOk()->assertSee('Thor')->assertSee('Mia')->assertSee('Kiwi')->assertDontSee('Jabu');
        $this->get('/pacientes?q=4321')->assertOk()->assertSee('Thor')->assertSee('Mia')->assertDontSee('Kiwi');
        $this->get('/pacientes?q=rafael')->assertOk()->assertSee('Mia')->assertSee('Kiwi')->assertDontSee('Thor');
        $this->get('/pacientes?q=985112000000001')->assertOk()->assertSee('Thor')->assertDontSee('Mia');
        $this->get('/pacientes?especie=bird')->assertOk()->assertSee('Kiwi')->assertDontSee('Thor');
        $this->get('/pacientes?q=%25')->assertOk()->assertSee('Nenhum paciente encontrado'); // curinga do LIKE é escapado
        $this->get('/responsaveis?q=souza')->assertOk()->assertSee('Marina Souza')->assertDontSee('Rafael Lima');
    }

    public function test_cadastro_rapido_cria_paciente_e_responsavel_vinculados_e_audita(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $res = $this->post('/pacientes', $this->newPatient(['microchip' => '985 112.000-000099']));
        $patient = $this->patient('Bolinha');
        $res->assertRedirect('/pacientes/'.$patient?->id);

        $this->assertSame(Seed::TENANT_A, $patient?->tenant_id);
        $this->assertSame('985112000000099', $patient?->microchip);
        $guardian = $this->ctx()->runAsSystem(fn () => Guardian::query()->where('name', 'Tutor Novo (fictício)')->first());
        $this->assertSame('21999990000', $guardian?->phone);
        $this->assertSame(Seed::TENANT_A, $guardian?->tenant_id);
        $pivot = $this->ctx()->runAsSystem(fn () => DB::table('guardian_patient')->where('patient_id', $patient?->id)->first());
        $this->assertSame([Seed::TENANT_A, $guardian?->id, 'primary'], [$pivot?->tenant_id, $pivot?->guardian_id, $pivot?->relationship]);
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'patient.created')->where('target_id', $patient?->id)->exists()));
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'guardian.created')->where('target_id', $guardian?->id)->exists()));

        $this->get('/pacientes/'.$patient?->id)->assertOk()->assertSee('Bolinha')->assertSee('Tutor Novo (fictício)')->assertSee('(21) 99999-0000');
    }

    public function test_idade_aproximada_gera_data_estimada(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/pacientes', $this->newPatient(['name' => 'Estimado', 'age_years' => 3, 'age_months' => 2]))->assertSessionHasNoErrors();
        $p = $this->patient('Estimado');
        $this->assertTrue($p?->birth_date_estimated);
        $this->assertSame(now()->subYears(3)->subMonths(2)->format('Y-m-d'), $p?->birth_date?->format('Y-m-d'));
        $this->assertSame('~3 anos e 2 meses', $p?->ageLabel());
    }

    public function test_telefone_ou_email_obrigatorio_e_validacoes(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/pacientes', $this->newPatient(['g_phone' => '']))->assertSessionHasErrors(['g_phone', 'g_email']);
        $this->post('/pacientes', $this->newPatient(['g_phone' => '123']))->assertSessionHasErrors('g_phone');
        $this->post('/pacientes', $this->newPatient(['g_document' => '111.111.111-11']))->assertSessionHasErrors('g_document');
        $this->post('/pacientes', $this->newPatient(['species' => 'dragao']))->assertSessionHasErrors('species');
        $this->post('/pacientes', $this->newPatient(['birth_date' => now()->addDay()->format('Y-m-d')]))->assertSessionHasErrors('birth_date');
        $this->post('/pacientes', $this->newPatient(['microchip' => '<script>']))->assertSessionHasErrors('microchip');
        $this->assertNull($this->patient('Bolinha'));
    }

    public function test_aviso_de_responsavel_duplicado_e_confirmacao(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->from('/pacientes/novo')->post('/pacientes', $this->newPatient(['g_phone' => '11987654321']))
            ->assertRedirect('/pacientes/novo')->assertSessionHas('duplicates');
        $this->assertNull($this->patient('Bolinha'));
        $this->get('/pacientes/novo')->assertSee('Possível cadastro repetido')->assertSee('Marina Souza (fictícia)');

        $this->post('/pacientes', $this->newPatient(['g_phone' => '11987654321', 'confirm_duplicate' => '1']))->assertSessionHasNoErrors();
        $this->assertNotNull($this->patient('Bolinha'));
    }

    public function test_duplicidade_nunca_considera_outra_clinica(): void
    {
        // Telefone da Helena (clínica B) cadastrado na clínica A: não há aviso, e nada da B é revelado.
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/pacientes', $this->newPatient(['g_phone' => '31988887777']))->assertSessionMissing('duplicates')->assertSessionHasNoErrors();
        $this->assertNotNull($this->patient('Bolinha'));
    }

    public function test_paciente_para_responsavel_existente_e_vinculos(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->get('/pacientes/novo?responsavel='.self::MARINA)->assertOk()->assertSee('Responsável: Marina Souza (fictícia)');
        $this->post('/pacientes', ['name' => 'Pipoca', 'species' => 'rodent', 'species_detail' => 'Hamster sírio', 'sex' => 'female', 'neutered' => 'no', 'guardian_id' => self::MARINA])
            ->assertSessionHasNoErrors();
        $pipoca = $this->patient('Pipoca');
        $this->get('/responsaveis/'.self::MARINA)->assertOk()->assertSee('Pipoca')->assertSee('Thor');

        // Mesmo nome + espécie para o mesmo responsável → aviso.
        $this->post('/pacientes', ['name' => 'Pipoca', 'species' => 'rodent', 'sex' => 'female', 'neutered' => 'no', 'guardian_id' => self::MARINA])
            ->assertSessionHas('duplicates');

        $this->get('/pacientes/'.$pipoca?->id.'?vincular=rafael')->assertOk()->assertSee('Rafael Lima (fictício)');
        $this->post('/pacientes/'.$pipoca?->id.'/responsaveis', ['guardian_id' => self::RAFAEL, 'relationship' => 'co_guardian'])->assertSessionHasNoErrors();
        $this->post('/pacientes/'.$pipoca?->id.'/responsaveis', ['guardian_id' => self::RAFAEL, 'relationship' => 'authorized'])->assertSessionHasErrors('guardian_id');
        $this->delete('/pacientes/'.$pipoca?->id.'/responsaveis/'.self::MARINA)->assertSessionHasNoErrors();
        $this->delete('/pacientes/'.$pipoca?->id.'/responsaveis/'.self::RAFAEL)->assertSessionHasErrors('guardian_id'); // último responsável
        $this->assertSame([self::RAFAEL], $this->ctx()->runAsSystem(fn () => DB::table('guardian_patient')->where('patient_id', $pipoca?->id)->pluck('guardian_id')->all()));
    }

    public function test_obito_exige_data_e_e_auditado(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $base = ['name' => 'Thor', 'species' => 'canine', 'sex' => 'male', 'neutered' => 'yes', 'birth_date' => '2019-03-10'];
        $this->put('/pacientes/'.self::THOR, [...$base, 'status' => 'deceased'])->assertSessionHasErrors('deceased_on');
        $this->put('/pacientes/'.self::THOR, [...$base, 'status' => 'deceased', 'deceased_on' => '2018-01-01'])->assertSessionHasErrors('deceased_on');
        $this->put('/pacientes/'.self::THOR, [...$base, 'status' => 'deceased', 'deceased_on' => now()->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'patient.deceased')->where('target_id', self::THOR)->exists()));
        $this->get('/pacientes')->assertDontSee('Thor');
        $this->get('/pacientes?todos=1')->assertSee('Thor')->assertSee('Óbito');
    }

    public function test_financeiro_so_consulta_e_ve_cpf_mascarado(): void
    {
        $this->loginInto('davi', Seed::TENANT_A);
        $this->get('/pacientes')->assertOk()->assertSee('Thor')->assertDontSee('Novo paciente');
        $this->get('/responsaveis/'.self::MARINA)->assertOk()->assertDontSee('52998224725')->assertSee('4725');
        $this->get('/pacientes/novo')->assertForbidden();
        $this->post('/pacientes', $this->newPatient())->assertForbidden();
        $this->get('/pacientes/'.self::THOR.'/editar')->assertForbidden();
        $this->put('/responsaveis/'.self::MARINA, ['name' => 'X', 'phone' => '11987654321', 'status' => 'active'])->assertForbidden();
        $this->post('/sair');

        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/responsaveis/'.self::MARINA)->assertOk()->assertSee('52998224725');
    }

    public function test_responsavel_editado_e_arquivado_sai_da_busca_padrao(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->put('/responsaveis/'.self::RAFAEL, ['name' => 'Rafael Lima (fictício)', 'phone' => '11912345678', 'city' => 'Cidade Fictícia', 'state' => 'sp', 'status' => 'archived'])
            ->assertRedirect('/responsaveis/'.self::RAFAEL)->assertSessionHasNoErrors();
        $this->get('/responsaveis')->assertDontSee('Rafael Lima');
        $this->get('/responsaveis?arquivados=1')->assertSee('Rafael Lima')->assertSee('Arquivado');
        $this->assertSame('SP', $this->ctx()->runAsSystem(fn () => Guardian::query()->find(self::RAFAEL)?->state));
    }

    // ---------------- Isolamento entre clínicas ----------------

    #[Group('tenant-isolation')]
    public function test_isolamento_paciente_e_responsavel_de_outra_clinica_respondem_404(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/pacientes')->assertOk()->assertSee('Jabu')->assertDontSee('Thor')->assertDontSee('Marina');
        $this->get('/pacientes?q=marina')->assertOk()->assertDontSee('Thor');
        $this->get('/responsaveis?q=11987654321')->assertOk()->assertDontSee('Marina');
        $this->get('/pacientes/'.self::THOR)->assertNotFound();
        $this->get('/pacientes/'.self::THOR.'/editar')->assertNotFound();
        $this->put('/pacientes/'.self::THOR, ['name' => 'Invadido', 'species' => 'canine', 'sex' => 'male', 'neutered' => 'yes'])->assertNotFound();
        $this->get('/responsaveis/'.self::MARINA)->assertNotFound();
        $this->get('/pacientes/novo?responsavel='.self::MARINA)->assertOk()->assertDontSee('Marina'); // ignora o ID de outra clínica
        $this->delete('/pacientes/'.self::MIA.'/responsaveis/'.self::RAFAEL)->assertNotFound();
        $this->assertSame('Thor', $this->ctx()->runAsSystem(fn () => Patient::query()->find(self::THOR)?->name));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_nao_vincula_responsavel_ou_paciente_de_outra_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        // Paciente novo da B tentando usar responsável da A.
        $this->post('/pacientes', ['name' => 'Cruzado', 'species' => 'feline', 'sex' => 'male', 'neutered' => 'no', 'guardian_id' => self::MARINA])
            ->assertSessionHasErrors('guardian_id');
        // Paciente da B + responsável da A.
        $this->post('/pacientes/'.self::JABU.'/responsaveis', ['guardian_id' => self::MARINA, 'relationship' => 'co_guardian'])
            ->assertSessionHasErrors('guardian_id');
        // Busca de candidatos para vincular não mostra a outra clínica.
        $this->get('/pacientes/'.self::JABU.'?vincular=marina')->assertOk()->assertDontSee('Marina');
        $this->assertNull($this->patient('Cruzado'));
        $this->assertSame([self::HELENA], $this->ctx()->runAsSystem(fn () => DB::table('guardian_patient')->where('patient_id', self::JABU)->pluck('guardian_id')->all()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_vinculo_entre_clinicas_mesmo_em_modo_sistema(): void
    {
        $this->expectException(QueryException::class);
        $this->ctx()->runAsSystem(fn () => DB::table('guardian_patient')->insert([
            'tenant_id' => Seed::TENANT_B, 'guardian_id' => self::MARINA, 'patient_id' => self::JABU, 'relationship' => 'primary',
        ]));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_painel_conta_so_pacientes_da_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/painel')->assertOk()->assertSee('pacientes ativos');
        $count = $this->ctx()->runForTenant(Seed::TENANT_B, fn () => Patient::query()->where('status', 'active')->count());
        $this->assertSame(2, $count);
    }
}
