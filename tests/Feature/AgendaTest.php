<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AuditEvent;
use App\Services\ScheduleService;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Agenda (Fase 1): fuso por unidade, conflito/encaixe, status, permissões e isolamento A/B. */
final class AgendaTest extends TestCase
{
    private const THOR = '01K7FV0000000000000PTA0001';

    private const MIA = '01K7FV0000000000000PTA0002';

    private const KIWI = '01K7FV0000000000000PTA0003';

    private const JABU = '01K7FV0000000000000PTB0001';

    private const UNIT_A1 = '01K7FV000000000000000TNA01';

    private const UNIT_A2 = '01K7FV000000000000000TNA02';

    private const UNIT_B1 = '01K7FV000000000000000TNB01';

    private const APPT_THOR_TODAY = '01K7FV0000000000000APA0001';

    private function ctx(): TenantContext
    {
        return app(TenantContext::class);
    }

    private function typeId(string $tenantId, string $name = 'Consulta'): string
    {
        return (string) $this->ctx()->runForTenant($tenantId, fn () => AppointmentType::query()->where('name', $name)->value('id'));
    }

    private function future(int $days = 3): string
    {
        return now('America/Sao_Paulo')->addDays($days)->format('Y-m-d');
    }

    /** @param  array<string, mixed>  $over */
    private function payload(array $over = []): array
    {
        return [
            'unit_id' => self::UNIT_A1, 'patient_id' => self::KIWI, 'appointment_type_id' => $this->typeId(Seed::TENANT_A),
            'professional_membership_id' => $this->membershipId('bruno', Seed::TENANT_A),
            'date' => $this->future(), 'time' => '10:00', 'duration_minutes' => 30, ...$over,
        ];
    }

    private function latestFor(string $patientId): ?Appointment
    {
        return $this->ctx()->runAsSystem(fn () => Appointment::query()->where('patient_id', $patientId)->latest('created_at')->first());
    }

    public function test_recepcao_agenda_no_fuso_da_unidade_e_grava_utc(): void
    {
        // Unidade Zona Sul passa a usar o fuso de Manaus (UTC−4): 10:00 local = 14:00 UTC.
        $this->ctx()->runAsSystem(fn () => DB::table('units')->where('id', self::UNIT_A2)->update(['timezone' => 'America/Manaus']));
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/agenda', $this->payload(['unit_id' => self::UNIT_A2]))->assertRedirect()->assertSessionHasNoErrors();

        $a = $this->latestFor(self::KIWI);
        $this->assertSame($this->future().' 14:00:00', $a?->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->future().' 14:30:00', $a?->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('scheduled', $a?->status);
        $this->assertTrue($this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'appointment.created')->where('target_id', $a?->id)->exists()));

        $this->get('/agenda?unidade='.self::UNIT_A2.'&data='.$this->future())->assertOk()->assertSee('Kiwi')->assertSee('10:00–10:30')->assertSee('America/Manaus');
        $this->get('/agenda?unidade='.self::UNIT_A1.'&data='.$this->future())->assertOk()->assertDontSee('Kiwi');
    }

    public function test_conflito_do_profissional_e_encaixe(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/agenda', $this->payload())->assertSessionHasNoErrors();
        // Sobrepõe 10:15–10:45 com o mesmo profissional → recusado com mensagem clara.
        $this->post('/agenda', $this->payload(['patient_id' => self::MIA, 'time' => '10:15']))
            ->assertSessionHasErrors(['starts_at' => 'O profissional já tem Kiwi das 10:00 às 10:30. Escolha outro horário ou marque como encaixe.']);
        // Encostado (10:30) não é conflito; outro profissional também não; encaixe permite sobrepor.
        $this->post('/agenda', $this->payload(['patient_id' => self::MIA, 'time' => '10:30']))->assertSessionHasNoErrors();
        $this->post('/agenda', $this->payload(['patient_id' => self::THOR, 'time' => '10:15', 'professional_membership_id' => $this->membershipId('ana', Seed::TENANT_A)]))->assertSessionHasNoErrors();
        $this->post('/agenda', $this->payload(['patient_id' => self::THOR, 'time' => '10:10', 'is_walk_in' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(4, $this->ctx()->runForTenant(Seed::TENANT_A, fn () => Appointment::query()->where('starts_at', '>=', now()->addDays(2))->count()));
    }

    public function test_cancelado_libera_o_horario_e_reabrir_exige_horario_livre(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/agenda', $this->payload());
        $first = $this->latestFor(self::KIWI);
        $this->post("/agenda/{$first?->id}/status", ['to' => 'cancelled', 'reason' => 'Tutor pediu para remarcar'])->assertSessionHasNoErrors();
        $this->post('/agenda', $this->payload(['patient_id' => self::MIA]))->assertSessionHasNoErrors(); // mesmo horário, agora livre
        $this->post("/agenda/{$first?->id}/status", ['to' => 'scheduled'])->assertSessionHasErrors('starts_at');
        $this->assertSame('Tutor pediu para remarcar', $this->ctx()->runAsSystem(fn () => Appointment::query()->find($first?->id)?->cancel_reason));
    }

    public function test_fluxo_de_status_e_permissao_para_atender(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $id = self::APPT_THOR_TODAY;
        $this->post("/agenda/{$id}/status", ['to' => 'completed'])->assertSessionHasErrors('status'); // pulo inválido
        $this->post("/agenda/{$id}/status", ['to' => 'confirmed'])->assertSessionHasNoErrors();
        $this->post("/agenda/{$id}/status", ['to' => 'waiting', 'back' => 'agenda'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/agenda')->assertOk()->assertSee('Fila de espera agora (1)');
        // Recepção não inicia atendimento (ato do profissional).
        $this->post("/agenda/{$id}/status", ['to' => 'in_progress'])->assertForbidden();
        $this->post('/sair');

        $this->loginInto('bruno', Seed::TENANT_A);
        $this->post("/agenda/{$id}/status", ['to' => 'in_progress'])->assertSessionHasNoErrors();
        $this->post("/agenda/{$id}/status", ['to' => 'completed'])->assertSessionHasNoErrors();
        $a = $this->ctx()->runAsSystem(fn () => Appointment::query()->find($id));
        $this->assertSame('completed', $a?->status);
        $this->assertNotNull($a?->confirmed_at);
        $this->assertNotNull($a?->checked_in_at);
        $this->assertNotNull($a?->started_at);
        $this->assertNotNull($a?->completed_at);
        // Concluído não volta nem é reagendado.
        $this->post("/agenda/{$id}/status", ['to' => 'scheduled'])->assertSessionHasErrors('status');
        $this->put("/agenda/{$id}", $this->payload(['patient_id' => self::THOR]))->assertSessionHasErrors('starts_at');
        $this->assertSame(4, $this->ctx()->runAsSystem(fn () => AuditEvent::query()->where('action', 'appointment.status_changed')->where('target_id', $id)->count()));
    }

    public function test_reagendar_muda_horario_e_mantem_paciente(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/agenda/'.self::APPT_THOR_TODAY.'/editar')->assertOk()->assertSee('Reagendar')->assertSee('Thor');
        $this->put('/agenda/'.self::APPT_THOR_TODAY, $this->payload(['patient_id' => self::KIWI, 'time' => '16:00']))->assertRedirect('/agenda/'.self::APPT_THOR_TODAY);
        $a = $this->ctx()->runAsSystem(fn () => Appointment::query()->find(self::APPT_THOR_TODAY));
        $this->assertSame(self::THOR, $a?->patient_id); // paciente não muda pelo reagendamento
        $this->assertSame($this->future().' 19:00:00', $a?->starts_at->format('Y-m-d H:i:s')); // 16:00 em São Paulo
    }

    public function test_permissoes_financeiro_le_estoque_nao_acessa(): void
    {
        $this->loginInto('davi', Seed::TENANT_A);
        $this->get('/agenda')->assertOk()->assertSee('Thor')->assertDontSee('Novo agendamento');
        $this->get('/agenda/novo')->assertForbidden();
        $this->post('/agenda', $this->payload())->assertForbidden();
        $this->post('/agenda/'.self::APPT_THOR_TODAY.'/status', ['to' => 'confirmed'])->assertForbidden();
        $this->get('/configuracoes/tipos-de-atendimento')->assertForbidden();
    }

    public function test_tipos_padrao_criados_e_configuracao_valida_contraste(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/configuracoes/tipos-de-atendimento')->assertOk()->assertSee('Consulta')->assertSee('Banho e tosa');
        $this->post('/configuracoes/tipos-de-atendimento', ['name' => 'Teleorientação', 'duration_minutes' => 20, 'color' => '#fafafa'])->assertSessionHasErrors('color');
        $this->post('/configuracoes/tipos-de-atendimento', ['name' => 'Consulta', 'duration_minutes' => 20, 'color' => '#1d4e89'])->assertSessionHasErrors('name');
        $this->post('/configuracoes/tipos-de-atendimento', ['name' => 'Teleorientação', 'duration_minutes' => 20, 'color' => '#1D4E89'])->assertSessionHasNoErrors();
        $tele = $this->typeId(Seed::TENANT_A, 'Teleorientação');
        $this->put("/configuracoes/tipos-de-atendimento/{$tele}", ['name' => 'Teleorientação', 'duration_minutes' => 25, 'color' => '#1d4e89', 'active' => '0'])->assertSessionHasNoErrors();
        $this->get('/agenda/novo?paciente='.self::KIWI)->assertOk()->assertDontSee('>Teleorientação (', false); // não aparece como opção
        // Nova clínica ganha os tipos padrão na primeira visita à agenda.
        $this->assertSame(6, $this->ctx()->runForTenant(Seed::TENANT_B, fn () => AppointmentType::query()->count()));
    }

    public function test_unidade_restrita_do_membro_e_respeitada(): void
    {
        $carla = $this->membershipId('carla', Seed::TENANT_A);
        $this->ctx()->runAsSystem(fn () => DB::table('membership_units')->insert(['tenant_id' => Seed::TENANT_A, 'membership_id' => $carla, 'unit_id' => self::UNIT_A2]));
        $this->loginInto('carla', Seed::TENANT_A);
        $this->get('/agenda?unidade='.self::UNIT_A1)->assertOk()->assertSee('Aurora — Zona Sul')->assertDontSee('Thor'); // cai na unidade permitida
        $this->post('/agenda', $this->payload(['unit_id' => self::UNIT_A1]))->assertSessionHasErrors('unit_id');
        $this->post('/agenda', $this->payload(['unit_id' => self::UNIT_A2]))->assertSessionHasNoErrors();
    }

    public function test_validacoes(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $this->post('/agenda', $this->payload(['time' => '25:00']))->assertSessionHasErrors('time');
        $this->post('/agenda', $this->payload(['duration_minutes' => 600]))->assertSessionHasErrors('duration_minutes');
        $this->post('/agenda', $this->payload(['professional_membership_id' => $this->membershipId('davi', Seed::TENANT_A)]))->assertSessionHasErrors('professional_membership_id'); // financeiro não atende
        $this->post('/agenda', $this->payload(['patient_id' => null]))->assertSessionHasErrors('patient_id');
        $this->assertNull($this->latestFor(self::KIWI));
    }

    public function test_paciente_e_painel_mostram_agenda(): void
    {
        $this->loginInto('ana', Seed::TENANT_A);
        $this->get('/pacientes/'.self::THOR)->assertOk()->assertSee('Próximos agendamentos')->assertSee('Consulta')->assertSee('Agendar');
        // Hoje há 2 agendamentos fictícios na clínica A (09:00 e 10:00); a lista "próximos" depende da hora atual.
        $this->get('/painel')->assertOk()->assertSee('agendamentos hoje')->assertSeeInOrder(['>2<', 'agendamentos hoje'], false);
    }

    // ---------------- Isolamento entre clínicas ----------------

    #[Group('tenant-isolation')]
    public function test_isolamento_agenda_e_agendamento_de_outra_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $this->get('/agenda')->assertOk()->assertSee('Jabu')->assertDontSee('Thor')->assertDontSee('Mia');
        $this->get('/agenda?unidade='.self::UNIT_A1)->assertOk()->assertDontSee('Thor'); // unidade de A ignorada
        $this->get('/agenda/'.self::APPT_THOR_TODAY)->assertNotFound();
        $this->get('/agenda/'.self::APPT_THOR_TODAY.'/editar')->assertNotFound();
        $this->post('/agenda/'.self::APPT_THOR_TODAY.'/status', ['to' => 'cancelled'])->assertNotFound();
        $this->get('/painel')->assertOk()->assertDontSee('Thor');
        $this->assertSame('scheduled', $this->ctx()->runAsSystem(fn () => Appointment::query()->find(self::APPT_THOR_TODAY)?->status));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_nao_agenda_com_dados_de_outra_clinica(): void
    {
        $this->loginInto('diana', Seed::TENANT_B);
        $base = [
            'unit_id' => self::UNIT_B1, 'patient_id' => self::JABU, 'appointment_type_id' => $this->typeId(Seed::TENANT_B),
            'professional_membership_id' => $this->membershipId('diana', Seed::TENANT_B), 'date' => $this->future(), 'time' => '09:00', 'duration_minutes' => 30,
        ];
        $this->post('/agenda', [...$base, 'patient_id' => self::THOR])->assertSessionHasErrors('patient_id');
        $this->post('/agenda', [...$base, 'unit_id' => self::UNIT_A1])->assertSessionHasErrors('unit_id');
        $this->post('/agenda', [...$base, 'appointment_type_id' => $this->typeId(Seed::TENANT_A)])->assertSessionHasErrors('appointment_type_id');
        $this->post('/agenda', [...$base, 'professional_membership_id' => $this->membershipId('bruno', Seed::TENANT_A)])->assertSessionHasErrors('professional_membership_id');
        $this->get('/agenda/novo?paciente='.self::THOR)->assertOk()->assertDontSee('Thor');
        $this->get('/agenda/novo?buscar=thor')->assertOk()->assertDontSee('Thor');
        $this->post('/agenda', $base)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->ctx()->runAsSystem(fn () => Appointment::query()->where('tenant_id', Seed::TENANT_B)->where('patient_id', self::THOR)->count()));
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_conflito_nao_considera_outra_clinica(): void
    {
        // Eva atende nas duas clínicas: o vínculo da B é outro, então a agenda da A não bloqueia a B.
        $evaA = $this->membershipId('eva', Seed::TENANT_A);
        $evaB = $this->membershipId('eva', Seed::TENANT_B);
        $this->assertNotSame($evaA, $evaB);
        $this->loginInto('ana', Seed::TENANT_A);
        $this->post('/agenda', $this->payload(['professional_membership_id' => $evaA]))->assertSessionHasNoErrors();
        $this->post('/sair');
        $this->loginInto('diana', Seed::TENANT_B);
        $this->post('/agenda', ['unit_id' => self::UNIT_B1, 'patient_id' => self::JABU, 'appointment_type_id' => $this->typeId(Seed::TENANT_B),
            'professional_membership_id' => $evaB, 'date' => $this->future(), 'time' => '10:00', 'duration_minutes' => 30])->assertSessionHasNoErrors();
    }

    #[Group('tenant-isolation')]
    public function test_isolamento_fk_composta_recusa_agendamento_cruzado_mesmo_em_modo_sistema(): void
    {
        $this->expectException(QueryException::class);
        $this->ctx()->runAsSystem(fn () => DB::table('appointments')->insert([
            'id' => '01K7FV0000000000000APX0001', 'tenant_id' => Seed::TENANT_B, 'unit_id' => self::UNIT_B1,
            'patient_id' => self::THOR, // paciente da A
            'appointment_type_id' => $this->typeId(Seed::TENANT_B), 'starts_at' => now(), 'ends_at' => now()->addMinutes(30),
            'status' => 'scheduled', 'is_walk_in' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_conversao_de_fuso(): void
    {
        $this->assertSame('2026-11-02 13:00', ScheduleService::toUtc('2026-11-02', '10:00', 'America/Sao_Paulo')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 14:00', ScheduleService::toUtc('2026-11-02', '10:00', 'America/Manaus')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 12:00', ScheduleService::toUtc('2026-11-02', '10:00', 'America/Noronha')->format('Y-m-d H:i'));
    }
}
