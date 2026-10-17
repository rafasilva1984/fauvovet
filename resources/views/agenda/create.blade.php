@extends('layouts.app')
@section('title', $appointment ? 'Reagendar' : 'Novo agendamento')

@php
    $editing = $appointment !== null;
    $typeOptions = $types->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.$t->duration_minutes.' min)'])->all();
    $firstType = $types->first();
    $duration = old('duration_minutes', $editing ? $appointment->starts_at->diffInMinutes($appointment->ends_at) : ($firstType?->duration_minutes ?? 30));
@endphp

@section('content')
    <x-page-header icon="calendar" eyebrow="Agenda" :title="$editing ? 'Reagendar' : 'Novo agendamento'" :description="$unit ? $unit->name.' · horários no fuso '.$unit->timezone : null" />

    @if (! $unit)
        <x-empty-state title="Nenhuma unidade disponível para o seu acesso" icon="building" />
    @else
        @if (! $patient)
            <x-card class="mb-6 max-w-3xl">
                <h2 class="mb-3 text-lg font-bold tracking-tight">1. Paciente</h2>
                <form method="GET" action="{{ route('agenda.create') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" role="search">
                    <input type="hidden" name="unidade" value="{{ $unit->id }}">
                    <input type="hidden" name="data" value="{{ $defaults['date'] }}">
                    <input type="hidden" name="hora" value="{{ $defaults['time'] }}">
                    <x-field name="buscar" label="Buscar paciente, responsável ou telefone" type="search" :value="$search" class="flex-1" autocomplete="off" />
                    <x-button variant="secondary"><x-icon name="search" /> Buscar</x-button>
                </form>
                @error('patient_id')<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                @if ($search !== '')
                    @if ($candidates->isEmpty())
                        <p class="mt-3 text-sm text-muted">Nenhum paciente encontrado.
                            @if ($fvCan('patients:write'))<a class="font-semibold text-link underline underline-offset-4" href="{{ route('patients.create') }}">Cadastrar paciente</a>@endif
                        </p>
                    @else
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($candidates as $c)
                                <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span><strong class="font-medium">{{ $c->name }}</strong> <span class="text-sm text-muted">{{ \App\Support\Species::label($c->species) }} · {{ $c->guardians->pluck('name')->join(', ') }}</span></span>
                                    <a href="{{ route('agenda.create', ['paciente' => $c->id, 'unidade' => $unit->id, 'data' => $defaults['date'], 'hora' => $defaults['time']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Escolher<span class="sr-only"> {{ $c->name }}</span></a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </x-card>
        @else
            <form method="POST" action="{{ $editing ? route('agenda.update', $appointment) : route('agenda.store') }}" class="flex max-w-3xl flex-col gap-6" novalidate>
                @csrf
                @if ($editing) @method('PUT') @endif
                @if ($errors->any())<x-alert>{{ $errors->first('starts_at') ?: 'Revise os campos destacados.' }}</x-alert>@endif
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                <x-card>
                    <h2 class="mb-1 text-lg font-bold tracking-tight">Paciente</h2>
                    <p><strong>{{ $patient->name }}</strong> <span class="text-muted">{{ \App\Support\Species::label($patient->species) }}</span>
                        @unless ($editing)
                            · <a class="text-sm font-semibold text-link underline underline-offset-4" href="{{ route('agenda.create', ['unidade' => $unit->id, 'data' => $defaults['date'], 'hora' => $defaults['time']]) }}">trocar</a>
                        @endunless
                    </p>
                </x-card>

                <x-card>
                    <h2 class="mb-4 text-lg font-bold tracking-tight">Horário</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @if ($units->count() > 1)
                            <x-select name="unit_id" label="Unidade" :options="$units->pluck('name', 'id')->all()" :value="$unit->id" required class="sm:col-span-2" />
                        @else
                            <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                        @endif
                        <x-select name="appointment_type_id" label="Tipo de atendimento" :options="$typeOptions" :value="$editing ? $appointment->appointment_type_id : $firstType?->id" required />
                        <x-select name="professional_membership_id" label="Profissional" :options="$professionals->mapWithKeys(fn ($m) => [$m->id => $m->user->name])->all()" :value="$editing ? $appointment->professional_membership_id : null" placeholder="Sem profissional definido" />
                        <x-field name="date" label="Data" type="date" required :value="$defaults['date']" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="time" label="Hora" type="time" step="300" required :value="$defaults['time']" />
                            <x-field name="duration_minutes" label="Duração (min)" type="number" min="5" max="480" step="5" required :value="$duration" />
                        </div>
                        <label class="flex min-h-11 items-center gap-3 text-sm sm:col-span-2">
                            <input type="hidden" name="is_walk_in" value="0">
                            <input type="checkbox" name="is_walk_in" value="1" class="size-4" @checked(old('is_walk_in', $editing && $appointment->is_walk_in))>
                            <span><strong>Encaixe</strong> — permite marcar mesmo se o profissional já tiver alguém nesse horário.</span>
                        </label>
                        <x-textarea name="notes" label="Observação para a recepção" :value="$editing ? $appointment->notes : null" hint="Ex.: trazer exames anteriores. Não registre informações clínicas aqui." rows="2" class="sm:col-span-2" />
                    </div>
                </x-card>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button>{{ $editing ? 'Salvar alterações' : 'Agendar' }}</x-button>
                    <a href="{{ $editing ? route('agenda.show', $appointment) : route('agenda.index', ['unidade' => $unit->id, 'data' => $defaults['date']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Voltar</a>
                </div>
            </form>
        @endif
    @endif
@endsection
