@extends('layouts.app')
@section('title', 'Agendamento de '.$appointment->patient->name)

@php($a = $appointment)
@push('styles')
    @include('agenda._type-colors', ['types' => collect([$a->type])])
@endpush

@section('content')
    <x-page-header :title="$a->patient->name" :description="$a->type->name.' · '.$a->unit->name">
        @if ($fvCan('schedule:write') && in_array($a->status, ['scheduled', 'confirmed', 'waiting'], true))
            <a href="{{ route('agenda.edit', $a) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-surface-2">Reagendar / editar</a>
        @endif
    </x-page-header>
    @error('status')<x-alert class="mb-6">{{ $message }}</x-alert>@enderror
    @error('starts_at')<x-alert class="mb-6">{{ $message }}</x-alert>@enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <x-card class="fv-at-{{ strtolower($a->appointment_type_id) }} border-l-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold tabular-nums">{{ ucfirst($a->starts_at->timezone($tz)->locale('pt_BR')->translatedFormat('l, d/m/Y')) }} · {{ $a->starts_at->timezone($tz)->format('H:i') }}–{{ $a->ends_at->timezone($tz)->format('H:i') }}</h2>
                @include('agenda._status', ['status' => $a->status])
            </div>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-muted">Profissional</dt><dd class="font-medium">{{ $a->professional?->user?->name ?? 'Sem profissional definido' }}</dd></div>
                <div><dt class="text-sm text-muted">Tipo</dt><dd class="font-medium">{{ $a->type->name }}@if ($a->is_walk_in) · encaixe @endif</dd></div>
                <div><dt class="text-sm text-muted">Paciente</dt><dd class="font-medium">
                    @if ($fvCan('patients:read'))<a href="{{ route('patients.show', $a->patient) }}" class="text-link underline underline-offset-4">{{ $a->patient->name }}</a>@else{{ $a->patient->name }}@endif
                    <span class="text-muted">· {{ \App\Support\Species::label($a->patient->species) }}</span></dd></div>
                <div><dt class="text-sm text-muted">Fuso</dt><dd class="font-medium">{{ $tz }}</dd></div>
                @if ($a->notes)
                    <div class="sm:col-span-2"><dt class="text-sm text-muted">Observação</dt><dd class="whitespace-pre-line">{{ $a->notes }}</dd></div>
                @endif
                @if ($a->status === 'cancelled' && $a->cancel_reason)
                    <div class="sm:col-span-2"><dt class="text-sm text-muted">Motivo do cancelamento</dt><dd>{{ $a->cancel_reason }}</dd></div>
                @endif
            </dl>
            <h3 class="mb-2 mt-6 font-semibold">Linha do tempo</h3>
            <ul class="grid gap-1 text-sm tabular-nums text-muted">
                @foreach (['confirmed_at' => 'Confirmado', 'checked_in_at' => 'Chegada', 'started_at' => 'Início do atendimento', 'completed_at' => 'Concluído', 'cancelled_at' => 'Cancelado'] as $field => $label)
                    @if ($a->{$field})
                        <li>{{ $label }}: {{ $a->{$field}->timezone($tz)->format('d/m H:i') }}</li>
                    @endif
                @endforeach
                @if ($a->checked_in_at && $a->started_at)
                    <li class="font-medium text-ink">Espera até o atendimento: {{ (int) $a->checked_in_at->diffInMinutes($a->started_at) }} min</li>
                @endif
            </ul>
            <div class="mt-6">@include('agenda._actions', ['a' => $a, 'back' => 'show'])</div>
        </x-card>

        <section aria-labelledby="contato-titulo">
            <h2 id="contato-titulo" class="mb-3 text-lg font-semibold">Responsáveis</h2>
            <ul class="flex flex-col gap-3">
                @foreach ($a->patient->guardians as $g)
                    <li><x-card><p class="font-semibold">{{ $g->name }}</p><dl class="mt-2 grid gap-3">@include('guardians._contact', ['guardian' => $g])</dl></x-card></li>
                @endforeach
            </ul>
        </section>
    </div>
    <p class="mt-6"><a href="{{ route('agenda.index', ['unidade' => $a->unit_id, 'data' => $a->starts_at->timezone($tz)->format('Y-m-d')]) }}" class="font-semibold text-link underline underline-offset-4">Voltar à agenda do dia</a></p>
@endsection
