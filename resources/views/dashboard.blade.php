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
    ];
@endphp

@section('content')
    <x-page-header :title="$greeting.', '.strtok(auth()->user()->name, ' ')" :description="$copy[0].' · '.$copy[1]" />

    <div class="mb-6 flex flex-wrap gap-1.5">
        @foreach ($fvRoles as $role)
            <x-badge>{{ $role }}</x-badge>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if ($patientCount !== null)
            <a href="{{ route('patients.index') }}">
                <x-card class="flex items-center gap-4 transition hover:border-brand/40">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link"><x-icon name="paw" /></span>
                    <span><span class="block text-2xl font-semibold tabular-nums">{{ $patientCount }}</span><span class="text-sm text-muted">{{ $patientCount === 1 ? 'paciente ativo' : 'pacientes ativos' }}</span></span>
                </x-card>
            </a>
        @endif
        <a href="{{ route('units.index') }}">
            <x-card class="flex items-center gap-4 transition hover:border-brand/40">
                <span class="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link"><x-icon name="building" /></span>
                <span><span class="block text-2xl font-semibold tabular-nums">{{ $unitCount }}</span><span class="text-sm text-muted">{{ $unitCount === 1 ? 'unidade' : 'unidades' }}</span></span>
            </x-card>
        </a>
        @if ($memberCount !== null)
            <a href="{{ route('team.index') }}">
                <x-card class="flex items-center gap-4 transition hover:border-brand/40">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link"><x-icon name="users" /></span>
                    <span><span class="block text-2xl font-semibold tabular-nums">{{ $memberCount }}</span><span class="text-sm text-muted">{{ $memberCount === 1 ? 'membro ativo' : 'membros ativos' }}</span></span>
                </x-card>
            </a>
        @endif
        <x-card class="flex items-center gap-4">
            <span class="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link"><x-icon name="shield" /></span>
            <span class="text-sm text-muted">Ambiente isolado: você vê somente dados de <strong class="text-ink">{{ $fvTenant?->trade_name }}</strong>.</span>
        </x-card>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section aria-labelledby="atendimento">
            <h2 id="atendimento" class="mb-3 text-lg font-semibold">Atendimento</h2>
            <x-empty-state title="Agenda e prontuário em construção" icon="clock">
                Responsáveis e pacientes já podem ser cadastrados. Agenda, atendimento e prontuário são as próximas entregas da Fase 1. Nada nesta tela é simulado: os números acima vêm do seu ambiente real.
            </x-empty-state>
        </section>
        <section aria-labelledby="atalhos">
            <h2 id="atalhos" class="mb-3 text-lg font-semibold">Atalhos</h2>
            <div class="flex flex-col gap-2">
                @if ($fvCan('patients:write'))
                    <a href="{{ route('patients.create') }}" class="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="plus" /> Novo paciente</a>
                @endif
                @if ($fvCan('patients:read'))
                    <a href="{{ route('patients.index') }}" class="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="search" /> Buscar paciente ou responsável</a>
                @endif
                @if ($fvCan('units:create'))
                    <a href="{{ route('units.index') }}#nova" class="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="plus" /> Nova unidade</a>
                @endif
                @if ($fvCan('theme:update'))
                    <a href="{{ route('settings.appearance') }}" class="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="palette" /> Personalizar aparência</a>
                @endif
                <a href="{{ route('units.index') }}" class="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"><x-icon name="building" /> Ver unidades</a>
            </div>
        </section>
    </div>

    @if ($activity !== null)
        <section aria-labelledby="atividade" class="mt-8">
            <h2 id="atividade" class="mb-3 text-lg font-semibold">Atividade recente</h2>
            @if ($activity->isEmpty())
                <x-empty-state title="Sem atividade registrada" />
            @else
                <x-card class="p-0">
                    <ul class="divide-y divide-line">
                        @foreach ($activity as $event)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                <span><strong class="font-medium">{{ $event->actor?->name ?? 'Sistema' }}</strong> <span class="text-muted">{{ $actions[$event->action] ?? $event->action }}</span></span>
                                <time class="text-xs tabular-nums text-muted" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</time>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </section>
    @endif
@endsection
