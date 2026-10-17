@extends('layouts.app')
@section('title', 'Painel')

@php
    $copy = [
        'management' => ['Gestão da clínica', 'Acompanhe a estrutura da clínica, equipe e atividade recente.'],
        'clinical' => ['Área clínica', 'Seu painel clínico mostrará agenda do dia, pacientes em atendimento e retornos.'],
        'reception' => ['Recepção', 'Seu painel mostrará agenda do dia, chegadas e fila de espera.'],
        'finance' => ['Financeiro', 'Seu painel mostrará recebimentos, caixa e pendências.'],
        'operations' => ['Operação', 'Seu painel mostrará as tarefas do seu setor.'],
    ][$kind];
    $hour = (int) now('America/Sao_Paulo')->format('G');
    $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
    $actions = [
        'auth.context_selected' => 'entrou no ambiente',
        'unit.created' => 'criou uma unidade',
        'tenant.theme_updated' => 'alterou a aparência',
        'tenant.created' => 'criou a clínica',
        'patient.created' => 'cadastrou um paciente',
        'patient.updated' => 'atualizou um paciente',
        'patient.deceased' => 'registrou um óbito',
        'patient.guardian_linked' => 'vinculou um responsável',
        'patient.guardian_unlinked' => 'desvinculou um responsável',
        'guardian.created' => 'cadastrou um responsável',
        'guardian.updated' => 'atualizou um responsável',
        'member.invited' => 'convidou uma pessoa',
        'invitation.accepted' => 'aceitou um convite',
        'invitation.revoked' => 'cancelou um convite',
        'member.updated' => 'alterou um acesso',
        'appointment.created' => 'agendou um atendimento',
        'appointment.updated' => 'reagendou um atendimento',
        'appointment.status_changed' => 'mudou o status de um agendamento',
        'appointment_type.created' => 'criou um tipo de atendimento',
        'appointment_type.updated' => 'alterou um tipo de atendimento',
        'encounter.created' => 'abriu um atendimento',
        'encounter.updated' => 'editou um rascunho de prontuário',
        'encounter.finalized' => 'finalizou e assinou um atendimento',
        'encounter.addendum_added' => 'registrou um adendo',
        'encounter.viewed' => 'consultou um prontuário',
        'patient.clinical_viewed' => 'consultou um prontuário',
        'patient.alert_added' => 'registrou um alerta clínico',
        'patient.alert_resolved' => 'resolveu um alerta clínico',
    ];
    $firstName = strtok(auth()->user()->name, ' ');
    $todayLabel = ucfirst(now('America/Sao_Paulo')->locale('pt_BR')->translatedFormat('l, d \\d\\e F'));
    $activityIcons = ['encounter' => 'stethoscope', 'appointment' => 'calendar', 'patient' => 'paw', 'guardian' => 'user', 'member' => 'users', 'invitation' => 'mail', 'unit' => 'building', 'tenant' => 'sparkles', 'auth' => 'shield'];
@endphp

@section('content')
    {{-- Faixa de boas-vindas: degradê da clínica, data, papéis e ações principais --}}
    <section aria-labelledby="saudacao" class="relative mb-8 animate-fade-up overflow-hidden rounded-[1.75rem] bg-brand fv-gradient p-6 text-on-brand shadow-glow sm:p-8">
        <div aria-hidden="true" class="fv-orb fv-orb-a absolute -right-16 -top-20 size-72 opacity-35"></div>
        <div aria-hidden="true" class="fv-dots absolute inset-0 opacity-20"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-[0.7rem] font-bold uppercase tracking-[0.12em] opacity-90 sm:text-xs sm:tracking-[0.18em]">{{ $todayLabel }} · {{ $copy[0] }}</p>
                <h1 id="saudacao" class="mt-2 text-[1.9rem] font-bold leading-tight tracking-tight sm:text-[2.4rem]">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="mt-2 opacity-90">{{ $copy[1] }}</p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($fvRoles as $role)
                        <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ring-white/25">{{ $role }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($fvCan('schedule:write'))
                    <a href="{{ route('agenda.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-[#0d1b21] shadow-lg transition hover:-translate-y-0.5"><x-icon name="calendar" :size="18" /> Novo agendamento</a>
                @endif
                @if ($fvCan('patients:write'))
                    <a href="{{ route('patients.create') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white/15 px-4 text-sm font-bold ring-1 ring-inset ring-white/30 transition hover:bg-white/25"><x-icon name="plus" :size="18" /> Novo paciente</a>
                @endif
            </div>
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @if ($today !== null)
            <x-stat :href="route('agenda.index')" icon="calendar" :value="$today['total']" :label="$today['total'] === 1 ? 'agendamento hoje' : 'agendamentos hoje'" :hint="$today['waiting'] ? $today['waiting'].' aguardando agora' : null" />
        @endif
        @if ($patientCount !== null)
            <x-stat :href="route('patients.index')" icon="paw" :value="$patientCount" :label="$patientCount === 1 ? 'paciente ativo' : 'pacientes ativos'" />
        @endif
        <x-stat :href="route('units.index')" icon="building" :value="$unitCount" :label="$unitCount === 1 ? 'unidade' : 'unidades'" />
        @if ($memberCount !== null)
            <x-stat :href="route('team.index')" icon="users" :value="$memberCount" :label="$memberCount === 1 ? 'membro ativo' : 'membros ativos'" />
        @endif
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section aria-labelledby="atendimento">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="atendimento" class="text-lg font-bold tracking-tight">Hoje na agenda</h2>
                @if ($today !== null)
                    <a href="{{ route('agenda.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-link">Ver agenda <x-icon name="arrow-right" :size="16" /></a>
                @endif
            </div>
            @if ($today !== null && $today['next']->isNotEmpty())
                <x-card class="p-2">
                    <ol class="relative">
                        @foreach ($today['next'] as $ap)
                            <li>
                                <a href="{{ route('agenda.show', $ap) }}" class="group flex items-center gap-4 rounded-2xl px-3 py-3 transition hover:bg-tint">
                                    <span class="w-14 shrink-0 text-right">
                                        <span class="block text-base font-bold tabular-nums text-ink">{{ $ap->starts_at->timezone($ap->unit->timezone)->format('H:i') }}</span>
                                    </span>
                                    <span aria-hidden="true" class="relative flex h-full flex-col items-center self-stretch">
                                        <span class="mt-2 size-2.5 rounded-full bg-brand ring-4 ring-tint"></span>
                                        <span class="w-px flex-1 bg-line"></span>
                                    </span>
                                    <x-avatar :name="$ap->patient->name" icon="paw" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold text-ink">{{ $ap->patient->name }}</span>
                                        <span class="block truncate text-sm text-muted">{{ $ap->type->name }}</span>
                                    </span>
                                    @include('agenda._status', ['status' => $ap->status])
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            @else
                <x-empty-state title="Nenhum atendimento pendente hoje" icon="calendar">
                    @if ($fvCan('schedule:write'))<a class="font-semibold text-link underline underline-offset-4" href="{{ route('agenda.create') }}">Agendar</a> · @endif
                    Para registrar um atendimento sem agendamento, abra a ficha do paciente e use “Novo atendimento”.
                </x-empty-state>
            @endif
        </section>

        <section aria-labelledby="atalhos">
            @if ($drafts !== null && $drafts->isNotEmpty())
                <h2 class="mb-3 flex items-center gap-2 text-lg font-bold tracking-tight"><span class="flex size-8 items-center justify-center rounded-xl bg-warning-bg text-warning"><x-icon name="stethoscope" :size="16" /></span> Prontuários em rascunho</h2>
                <x-card class="mb-6 p-2">
                    <ul>
                        @foreach ($drafts as $dr)
                            <li>
                                <a href="{{ route('encounters.show', $dr) }}" class="flex items-center gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-tint">
                                    <x-avatar :name="$dr->patient->name" icon="paw" :tone="\App\Support\Species::tone($dr->patient->species)" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ $dr->patient->name }}</span>
                                        <span class="block truncate text-xs text-muted">{{ $dr->started_at->timezone($dr->unit->timezone)->format('d/m H:i') }} · {{ $dr->author?->name }}</span>
                                    </span>
                                    <x-badge tone="warning" :dot="true">Rascunho</x-badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
            <h2 id="atalhos" class="mb-3 text-lg font-bold tracking-tight">Atalhos</h2>
            @php
                $shortcuts = array_values(array_filter([
                    $fvCan('schedule:write') ? [route('agenda.create'), 'Novo agendamento', 'calendar', 'Marcar horário'] : null,
                    $fvCan('patients:write') ? [route('patients.create'), 'Novo paciente', 'plus', 'Cadastro rápido'] : null,
                    $fvCan('patients:read') ? [route('patients.index'), 'Buscar paciente ou responsável', 'search', 'Nome, telefone ou microchip'] : null,
                    $fvCan('units:create') ? [route('units.index').'#nova', 'Nova unidade', 'building', 'Filial ou local'] : null,
                    $fvCan('theme:update') ? [route('settings.appearance'), 'Personalizar aparência', 'palette', 'Cores da clínica'] : null,
                    [route('units.index'), 'Ver unidades', 'building', 'Fusos e endereços'],
                ]));
            @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                @foreach ($shortcuts as [$href, $label, $icon, $hint])
                    <a href="{{ $href }}" class="fv-lift flex min-h-16 items-center gap-3 rounded-2xl border border-line bg-surface p-3 shadow-card">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-tint text-link"><x-icon :name="$icon" :size="20" /></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold leading-snug text-ink">{{ $label }}</span>
                            <span class="block truncate text-xs text-muted">{{ $hint }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-line bg-surface p-4 text-sm text-muted shadow-card">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success-bg text-success"><x-icon name="shield" :size="18" /></span>
                <span>Ambiente isolado: você vê somente dados de <strong class="text-ink">{{ $fvTenant?->trade_name }}</strong>.</span>
            </div>
        </section>
    </div>

    @if ($activity !== null)
        <section aria-labelledby="atividade" class="mt-8">
            <h2 id="atividade" class="mb-3 text-lg font-bold tracking-tight">Atividade recente</h2>
            @if ($activity->isEmpty())
                <x-empty-state title="Sem atividade registrada" icon="sparkles" />
            @else
                <x-card class="p-2">
                    <ul>
                        @foreach ($activity as $event)
                            <li class="flex items-center gap-3 rounded-2xl px-3 py-2.5">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-muted"><x-icon :name="$activityIcons[strtok($event->action, '.')] ?? 'sparkles'" :size="18" /></span>
                                <span class="min-w-0 flex-1 text-sm"><strong class="font-semibold">{{ $event->actor?->name ?? 'Sistema' }}</strong> <span class="text-muted">{{ $actions[$event->action] ?? $event->action }}</span></span>
                                <time class="shrink-0 text-xs tabular-nums text-muted" datetime="{{ $event->created_at->toIso8601String() }}" title="{{ $event->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}">{{ $event->created_at->locale('pt_BR')->diffForHumans() }}</time>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </section>
    @endif
@endsection
