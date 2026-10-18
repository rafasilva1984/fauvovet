<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Encounter;
use App\Models\ExamRequest;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Unit;
use App\Services\ScheduleService;
use App\Services\VaccinationService;
use App\Support\AuditLabels;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        $canAudit = $context->can('audit:read');
        $today = $context->can('schedule:read') ? $this->today($context) : null;

        return view('dashboard', [
            'kind' => Roles::dashboard($context->roles()),
            'stats' => $this->stats($context, $today),
            // Rascunhos de prontuário abertos na clínica (para não ficarem sem assinatura).
            'drafts' => $context->can('clinical:write')
                ? Encounter::query()->where('status', 'draft')->with(['patient:id,name,species', 'author:id,name', 'unit:id,timezone'])
                    ->orderBy('started_at')->limit(5)->get()
                : null,
            'vaccinesDue' => $context->can('vaccines:read') ? app(VaccinationService::class)->dueList(30, 5) : null,
            'today' => $today,
            // Leituras, downloads e buscas ficam na auditoria, mas não poluem o feed de atividade.
            'activity' => $canAudit ? AuditEvent::query()->whereNotIn('action', AuditLabels::QUIET)
                ->with('actor:id,name')->latest('id')->limit(8)->get() : null,
        ]);
    }

    /**
     * Indicadores do topo: pendências de trabalho do papel (no máximo 4). Contagens de estrutura (unidades, equipe)
     * só entram quando o papel não tem pendências clínicas ou de agenda para mostrar.
     *
     * @param  array{total: int, waiting: int, next: Collection<int, Appointment>}|null  $today
     * @return list<array{href: string, icon: string, value: int|string, label: string, hint: ?string}>
     */
    private function stats(TenantContext $context, ?array $today): array
    {
        $stats = [];
        if ($today !== null) {
            $stats[] = ['href' => route('agenda.index'), 'icon' => 'calendar', 'value' => $today['total'], 'label' => $today['total'] === 1 ? 'agendamento hoje' : 'agendamentos hoje',
                'hint' => $today['waiting'] ? $today['waiting'].' aguardando agora' : null];
        }
        if ($context->can('clinical:write')) {
            $drafts = Encounter::query()->where('status', 'draft')->count();
            $stats[] = ['href' => route('dashboard').'#rascunhos', 'icon' => 'stethoscope', 'value' => $drafts, 'label' => $drafts === 1 ? 'atendimento em rascunho' : 'atendimentos em rascunho', 'hint' => $drafts ? 'aguardando assinatura' : null];
        }
        if ($context->can('clinical:read')) {
            $open = ExamRequest::query()->whereNotIn('status', ['reviewed', 'cancelled'])->count();
            $toReview = ExamRequest::query()->where('status', 'received')->count();
            $stats[] = ['href' => route('exams.index'), 'icon' => 'flask', 'value' => $open, 'label' => $open === 1 ? 'exame em aberto' : 'exames em aberto', 'hint' => $toReview ? $toReview.' para revisar' : null];
        }
        if ($context->can('vaccines:read')) {
            $late = app(VaccinationService::class)->dueList(-1, 201)->count();
            $stats[] = ['href' => route('vaccinations.index'), 'icon' => 'syringe', 'value' => $late > 200 ? '200+' : $late, 'label' => $late === 1 ? 'vacina atrasada' : 'vacinas atrasadas', 'hint' => null];
        }
        if ($context->can('patients:read')) {
            $patients = Patient::query()->where('status', 'active')->count();
            $stats[] = ['href' => route('patients.index'), 'icon' => 'paw', 'value' => $patients, 'label' => $patients === 1 ? 'paciente ativo' : 'pacientes ativos', 'hint' => null];
        }
        if ($context->can('members:read')) {
            $members = Membership::query()->where('status', 'active')->count();
            $stats[] = ['href' => route('team.index'), 'icon' => 'users', 'value' => $members, 'label' => $members === 1 ? 'membro ativo' : 'membros ativos', 'hint' => null];
        }
        $units = Unit::query()->count();
        $stats[] = ['href' => route('units.index'), 'icon' => 'building', 'value' => $units, 'label' => $units === 1 ? 'unidade' : 'unidades', 'hint' => null];

        return array_slice($stats, 0, 4);
    }

    /**
     * Agenda de hoje na unidade ativa (ou em todas as unidades permitidas), no fuso de cada unidade.
     *
     * @return array{total: int, waiting: int, next: Collection<int, Appointment>}
     */
    private function today(TenantContext $context): array
    {
        $units = app(ScheduleService::class)->allowedUnits();
        $query = Appointment::query()->whereNotIn('status', ['cancelled'])
            ->where(function ($w) use ($units) {
                foreach ($units as $u) {
                    $start = now($u->timezone)->startOfDay();
                    $w->orWhere(fn ($q) => $q->where('unit_id', $u->id)
                        ->where('starts_at', '>=', $start->copy()->utc())->where('starts_at', '<', $start->copy()->addDay()->utc()));
                }
            });
        if ($units->isEmpty()) {
            $query->whereRaw('1 = 0');
        }

        return [
            'total' => (clone $query)->count(),
            'waiting' => (clone $query)->where('status', 'waiting')->count(),
            // Pendências do dia: quem está em atendimento ou aguardando aparece sempre, mesmo depois do horário
            // marcado (antes sumia do painel); depois os agendados ainda sem chegada, em ordem de horário.
            'next' => (clone $query)->whereIn('status', ['scheduled', 'confirmed', 'waiting', 'in_progress'])
                ->with(['patient:id,name', 'type:id,name', 'unit:id,name,timezone'])
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")->orderBy('starts_at')->limit(6)->get(),
        ];
    }
}
