<?php

namespace Tests\Feature;

use App\Clinical\ClinicalRecordLocked;
use App\Models\AuditEvent;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Services\VaccinationService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Vacinação: catálogo, registro com próxima dose, carteira, vencimentos, permissões e isolamento A/B. */
final class VaccinationTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const MIA = '01K7FV0000000000000PTA0002';

    private const KIWI = '01K7FV0000000000000PTA0003';

    private const JABU = '01K7FV0000000000000PTB0001';

    private function sys(\Closure $fn): mixed
    {
        return app(TenantContext::class)->runAsSystem($fn);
    }

    private function vaccine(string $tenant, string $name): Vaccine
    {
        return app(TenantContext::class)->runForTenant($tenant, function () use ($name) {
            app(VaccinationService::class)->ensureDefaults();

            return Vaccine::query()->where('name', $name)->firstOrFail();
        });
    }

    /** @param array<string, mixed> $over */
    private function register(string $patient, string $vaccineId, array $over = []): TestResponse
    {
        return $this->from("/pacientes/{$patient}")->post("/pacientes/{$patient}/vacinas", [
            'vaccine_id' => $vaccineId, 'applied_on' => now()->format('Y-m-d'), 'lot' => 'L123-FICT', ...$over,
        ]);
    }

    public function test_proxima_dose_pelo_protocolo(): void
    {
        $v = new Vaccine(['series_doses' => 3, 'series_interval_days' => 21, 'booster_interval_days' => 365]);
        $d = Carbon::parse('2026-01-10');
        $this->assertSame('2026-01-31', VaccinationService::nextDue($v, 1, $d)?->toDateString());
        $this->assertSame('2027-01-10', VaccinationService::nextDue($v, 3, $d)?->toDateString());
        $this->assertNull(VaccinationService::nextDue(new Vaccine(['series_doses' => 1, 'series_interval_days' => 0, 'booster_interval_days' => null]), 1, $d));
    }

    public function test_registrar_serie_calcula_dose_e_proxima_e_audita(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $poli = $this->vaccine(Seed::TENANT_A, 'Polivalente canina (V8/V10)');
        $kiwiIsBird = $this->register(self::KIWI, $poli->id)->assertSessionHasErrors('vaccine_id'); // espécie não indicada
        unset($kiwiIsBird);

        $this->register(self::MIA, $this->vaccine(Seed::TENANT_A, 'Polivalente felina (V4/V5)')->id, ['applied_on' => now()->subDays(42)->format('Y-m-d')])->assertSessionHasNoErrors();
        $this->register(self::MIA, $this->vaccine(Seed::TENANT_A, 'Polivalente felina (V4/V5)')->id, ['applied_on' => now()->subDays(21)->format('Y-m-d'), 'site' => 'Intramuscular'])->assertSessionHasNoErrors();
        $doses = $this->sys(fn () => Vaccination::query()->where('patient_id', self::MIA)->whereHas('vaccine', fn ($q) => $q->where('name', 'like', 'Polivalente felina%'))->orderBy('applied_on')->get());
        $this->assertSame([1, 2], $doses->pluck('dose_number')->all());
        $this->assertSame(now()->toDateString(), $doses->last()?->next_due_on?->toDateString()); // dose 3 = hoje (21 dias depois)
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'vaccination.registered')->where('target_id', self::MIA)->exists()));

        $this->get('/pacientes/'.self::MIA)->assertSee('Polivalente felina (V4/V5)')->assertSee('Vence hoje');
    }

    public function test_validacoes_lote_vencido_data_futura_e_lote_obrigatorio(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $raiva = $this->vaccine(Seed::TENANT_A, 'Antirrábica');
        $this->register(self::THOR, $raiva->id, ['lot_expires_on' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors('lot_expires_on');
        $this->register(self::THOR, $raiva->id, ['applied_on' => now()->addDay()->format('Y-m-d')])->assertSessionHasErrors('applied_on');
        $this->register(self::THOR, $raiva->id, ['lot' => ''])->assertSessionHasErrors('lot');
        $this->register(self::THOR, $raiva->id, ['site' => 'Na orelha'])->assertSessionHasErrors('site');
    }

    public function test_carteira_situacao_e_vencimentos_da_clinica(): void
    {
        $this->loginInto('carla', Seed::TENANT_A); // recepção lê carteira e vencimentos
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertSee('Vacinação')->assertSee('Atrasada há 35 dias')->assertDontSee('Registrar aplicação');
        $this->get('/vacinas')->assertOk()->assertSee('Thor')->assertSee('Mia')->assertSee('Atrasada há 35 dias')->assertSee('Vence em 15 dias');
        $this->get('/vacinas?dias=7')->assertOk()->assertSee('Thor')->assertDontSee('Vence em 15 dias');
        $this->get('/painel')->assertOk()->assertSee('Vacinas a vencer');
        // Uma dose nova (reforço) tira o Thor da lista.
        $this->post('/sair');
        $this->loginInto('bruno', Seed::TENANT_A);
        $this->register(self::THOR, $this->vaccine(Seed::TENANT_A, 'Polivalente canina (V8/V10)')->id, ['dose_number' => 4])->assertSessionHasNoErrors();
        $this->get('/vacinas?dias=7')->assertSee('Vacina registrada')->assertDontSee('Atrasada há 35 dias'); // Thor saiu da lista
    }

    public function test_anular_e_imutabilidade_e_carteira_pdf(): void
    {
        $this->loginInto('bruno', Seed::TENANT_A);
        $raiva = $this->vaccine(Seed::TENANT_A, 'Antirrábica');
        $this->register(self::THOR, $raiva->id, ['lot' => 'LOTE-ERRADO'])->assertSessionHasNoErrors();
        $v = $this->sys(fn () => Vaccination::query()->where('lot', 'LOTE-ERRADO')->firstOrFail());
        $this->post("/vacinas/aplicacoes/{$v->id}/anular", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/vacinas/aplicacoes/{$v->id}/anular", ['reason' => 'Lote digitado errado'])->assertSessionHasNoErrors();
        $this->get('/pacientes/'.self::THOR)->assertSee('Anulada: Lote digitado errado');

        $res = $this->get('/pacientes/'.self::THOR.'/vacinas/carteira')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
        $this->assertTrue($this->sys(fn () => AuditEvent::query()->where('action', 'vaccination.card_exported')->exists()));

        app(TenantContext::class)->runForTenant(Seed::TENANT_A, function () use ($v) {
            $this->expectException(ClinicalRecordLocked::class);
            Vaccination::query()->findOrFail($v->id)->delete();
        });
    }

    public function test_catalogo_da_clinica(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/configuracoes/vacinas')->assertOk()->assertSee('Polivalente canina (V8/V10)')->assertSee('reforço a cada 365 dias');
        $this->post('/configuracoes/vacinas', ['name' => 'Influenza equina (fictícia)', 'species' => ['equine'], 'series_doses' => 2, 'series_interval_days' => 30, 'booster_interval_days' => 180])->assertSessionHasNoErrors();
        $this->post('/configuracoes/vacinas', ['name' => 'Antirrábica', 'series_doses' => 1, 'series_interval_days' => 0])->assertSessionHasErrors('name');
        $this->post('/configuracoes/vacinas', ['name' => 'X', 'species' => ['dragao'], 'series_doses' => 1, 'series_interval_days' => 0])->assertSessionHasErrors('species.0');
        $this->post('/sair');
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/configuracoes/vacinas')->assertForbidden();
        $this->post('/pacientes/'.self::THOR.'/vacinas', ['vaccine_id' => 'x', 'applied_on' => now()->format('Y-m-d'), 'lot' => 'x'])->assertForbidden();
        $this->post('/sair');
        $this->loginInto('davi', Seed::TENANT_A); // financeiro: sem acesso a vacinas
        $this->get('/vacinas')->assertForbidden();
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertDontSee('Vacinação</h2>', false);
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_vacinas_entre_clinicas(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/vacinas?dias=90')->assertOk()->assertDontSee('Thor')->assertDontSee('Mia');
        $this->get('/pacientes/'.self::THOR.'/vacinas/carteira')->assertNotFound();
        $vA = $this->sys(fn () => Vaccination::query()->where('patient_id', self::THOR)->firstOrFail());
        $this->post("/vacinas/aplicacoes/{$vA->id}/anular", ['reason' => 'invasão'])->assertNotFound();
        $raivaA = $this->vaccine(Seed::TENANT_A, 'Antirrábica');
        $this->register(self::JABU, $raivaA->id)->assertNotFound(); // vacina do catálogo da A
        $this->get("/configuracoes/vacinas/{$raivaA->id}/editar")->assertNotFound();
        $this->assertNull($this->sys(fn () => Vaccination::query()->find($vA->id)?->voided_at));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_aplicacao_cruzada(): void
    {
        $raivaA = $this->vaccine(Seed::TENANT_A, 'Antirrábica');
        $this->expectException(QueryException::class);
        $this->sys(fn () => DB::table('vaccinations')->insert([
            'id' => '01K7FV0000000000000VXX0001', 'tenant_id' => Seed::TENANT_B, 'patient_id' => self::JABU, 'vaccine_id' => $raivaA->id,
            'dose_number' => 1, 'applied_on' => now()->toDateString(), 'lot' => 'x', 'applied_by_membership_id' => $this->membershipId('diana', Seed::TENANT_B),
            'created_by' => Seed::USERS['diana']['id'], 'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
