@extends('layouts.app')
@section('title', $patient->name)

@php
    $canWrite = $fvCan('patients:write');
    $speciesText = \App\Support\Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : '');
@endphp

@section('content')
    <x-page-header :title="$patient->name" :description="$speciesText">
        @if ($canWrite)
            <a href="{{ route('patients.edit', $patient) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-surface-2">Editar</a>
        @endif
    </x-page-header>

    @if ($patient->status === 'deceased')
        <x-alert tone="info" class="mb-6">Óbito registrado{{ $patient->deceased_on ? ' em '.$patient->deceased_on->format('d/m/Y') : '' }}.</x-alert>
    @elseif ($patient->status === 'archived')
        <x-alert tone="info" class="mb-6">Paciente arquivado: não aparece na busca padrão.</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <x-card>
            <h2 class="mb-4 text-lg font-semibold">Identificação</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-muted">Espécie</dt><dd class="font-medium">{{ $speciesText }}</dd></div>
                <div><dt class="text-sm text-muted">Raça</dt><dd class="font-medium">{{ $patient->breed ?: '—' }}</dd></div>
                <div><dt class="text-sm text-muted">Sexo</dt><dd class="font-medium">{{ \App\Models\Patient::SEX[$patient->sex] }}</dd></div>
                <div><dt class="text-sm text-muted">Castração</dt><dd class="font-medium">{{ \App\Models\Patient::NEUTERED[$patient->neutered] }}</dd></div>
                <div><dt class="text-sm text-muted">Nascimento</dt><dd class="font-medium">
                    @if ($patient->birth_date)
                        {{ $patient->birth_date_estimated ? '~' : '' }}{{ $patient->birth_date->format('d/m/Y') }} <span class="text-muted">({{ $patient->ageLabel() }})</span>
                    @else
                        —
                    @endif
                </dd></div>
                <div><dt class="text-sm text-muted">Pelagem / cor</dt><dd class="font-medium">{{ $patient->coat ?: '—' }}</dd></div>
                <div><dt class="text-sm text-muted">Microchip</dt><dd class="font-medium tabular-nums">{{ $patient->microchip ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-muted">Sinais particulares</dt><dd class="whitespace-pre-line font-medium">{{ $patient->marks ?: '—' }}</dd></div>
            </dl>
            <p class="mt-5 rounded-xl bg-surface-2 px-4 py-3 text-sm text-muted">Alergias, peso, vacinas e prontuário ficarão no módulo clínico (próximas entregas), visíveis só para a equipe clínica.</p>
        </x-card>

        <section aria-labelledby="resp-titulo" class="flex flex-col gap-4">
            <h2 id="resp-titulo" class="text-lg font-semibold">Responsáveis</h2>
            @error('guardian_id')<x-alert>{{ $message }}</x-alert>@enderror
            <ul class="flex flex-col gap-3">
                @foreach ($patient->guardians as $g)
                    <li>
                        <x-card>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('guardians.show', $g) }}" class="font-semibold text-link underline underline-offset-4">{{ $g->name }}</a>
                                    <p class="text-xs text-muted">{{ \App\Models\Patient::RELATIONSHIPS[$g->pivot->relationship] ?? '' }}</p>
                                </div>
                                @if ($canWrite && $patient->guardians->count() > 1)
                                    <form method="POST" action="{{ route('patients.guardians.unlink', [$patient, $g]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" class="text-sm">Desvincular<span class="sr-only"> {{ $g->name }}</span></x-button>
                                    </form>
                                @endif
                            </div>
                            <dl class="mt-3 grid gap-3">@include('guardians._contact', ['guardian' => $g])</dl>
                        </x-card>
                    </li>
                @endforeach
            </ul>

            @if ($canWrite)
                <x-card>
                    <h3 class="mb-3 font-semibold">Vincular outro responsável</h3>
                    <form method="GET" action="{{ route('patients.show', $patient) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" role="search">
                        <x-field name="vincular" label="Buscar responsável já cadastrado" type="search" :value="$term" class="flex-1" autocomplete="off" />
                        <x-button variant="secondary"><x-icon name="search" /> Buscar</x-button>
                    </form>
                    @if ($term !== '')
                        @if ($candidates->isEmpty())
                            <p class="mt-3 text-sm text-muted">Nenhum responsável encontrado. <a class="font-semibold text-link underline underline-offset-4" href="{{ route('guardians.create') }}">Cadastrar novo</a></p>
                        @else
                            <ul class="mt-3 divide-y divide-line">
                                @foreach ($candidates as $c)
                                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <span><strong class="font-medium">{{ $c->name }}</strong> <span class="text-sm text-muted">{{ \App\Models\Guardian::formatPhone($c->phone) }}</span></span>
                                        <form method="POST" action="{{ route('patients.guardians.link', $patient) }}" class="flex gap-2">
                                            @csrf
                                            <input type="hidden" name="guardian_id" value="{{ $c->id }}">
                                            <label for="rel-{{ $c->id }}" class="sr-only">Vínculo de {{ $c->name }}</label>
                                            <select id="rel-{{ $c->id }}" name="relationship" class="min-h-11 rounded-xl border border-line bg-surface px-2 text-sm">
                                                @foreach (\App\Models\Patient::RELATIONSHIPS as $v => $l)
                                                    <option value="{{ $v }}" @selected($v === 'co_guardian')>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                            <x-button variant="secondary">Vincular<span class="sr-only"> {{ $c->name }}</span></x-button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </x-card>
            @endif
        </section>
    </div>
    <p class="mt-6"><a href="{{ route('patients.index') }}" class="font-semibold text-link underline underline-offset-4">Voltar aos pacientes</a></p>
@endsection
