@extends('layouts.app')
@section('title', 'Agenda')

@if ($unit)
    @push('styles')
        @include('agenda._type-colors', ['types' => $appointments->pluck('type')->unique('id')])
    @endpush
@endif

@section('content')
    @if (! $unit)
        <x-page-header title="Agenda" />
        <x-empty-state title="Nenhuma unidade disponível para o seu acesso" icon="building" />
    @else
        @php
            $q = fn (array $over) => route('agenda.index', array_filter([
                'unidade' => $unit->id, 'data' => $day->format('Y-m-d'), 'visao' => $week ? 'semana' : null,
                'profissional' => $proId, 'canceladas' => $showCancelled ? 1 : null, ...$over,
            ], fn ($v) => $v !== null && $v !== ''));
            $title = $week
                ? 'Semana de '.$from->format('d/m').' a '.$from->copy()->addDays(6)->format('d/m/Y')
                : ucfirst($day->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y'));
            $isToday = $day->isSameDay(now($tz));
        @endphp
        <x-page-header title="Agenda" icon="calendar" :eyebrow="$unit->name" :description="'Horários no fuso '.$tz.'.'">
            @if ($fvCan('schedule:write'))
                <a href="{{ route('agenda.create', ['unidade' => $unit->id, 'data' => $day->format('Y-m-d')]) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo agendamento</a>
            @endif
        </x-page-header>

        <form method="GET" action="{{ route('agenda.index') }}" class="mb-4 grid grid-cols-2 items-end gap-3 rounded-[1.25rem] border border-line bg-surface p-3 shadow-card sm:p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_11rem_auto]">
            @if ($units->count() > 1)
                <x-select name="unidade" label="Unidade" :options="$units->pluck('name', 'id')->all()" :value="$unit->id" class="col-span-2 lg:col-span-1" />
            @else
                <input type="hidden" name="unidade" value="{{ $unit->id }}">
            @endif
            <x-select name="profissional" label="Profissional" :options="$professionals->mapWithKeys(fn ($m) => [$m->id => $m->user->name])->all()" :value="$proId" placeholder="Todos" />
            <x-field name="data" label="Data" type="date" :value="$day->format('Y-m-d')" />
            <input type="hidden" name="visao" value="{{ $week ? 'semana' : 'dia' }}">
            <x-button variant="secondary" class="col-span-2 lg:col-span-1">Ver</x-button>
        </form>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <nav aria-label="Navegar na agenda" class="flex flex-wrap items-center gap-1 rounded-2xl border border-line bg-surface p-1 shadow-card">
                <a href="{{ $q(['data' => $day->copy()->subDays($step)->format('Y-m-d')]) }}" class="inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-sm font-semibold hover:bg-surface-2"><x-icon name="chevron-left" :size="16" /> {{ $week ? 'Semana anterior' : 'Dia anterior' }}</a>
                <a href="{{ $q(['data' => now($tz)->format('Y-m-d')]) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold {{ $isToday ? 'bg-tint text-link' : 'hover:bg-surface-2' }}" @if ($isToday) aria-current="date" @endif>Hoje</a>
                <a href="{{ $q(['data' => $day->copy()->addDays($step)->format('Y-m-d')]) }}" class="inline-flex min-h-11 items-center gap-1 rounded-xl px-3 text-sm font-semibold hover:bg-surface-2">{{ $week ? 'Próxima semana' : 'Próximo dia' }} <x-icon name="chevron-right" :size="16" /></a>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-2xl border border-line bg-surface p-1 shadow-card">
                <a href="{{ $q(['visao' => null]) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold {{ $week ? 'hover:bg-surface-2' : 'bg-brand fv-gradient text-on-brand shadow-glow' }}" @unless ($week) aria-current="page" @endunless>Dia</a>
                <a href="{{ $q(['visao' => 'semana']) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold {{ $week ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'hover:bg-surface-2' }}" @if ($week) aria-current="page" @endif>Semana</a>
                </div>
                <a href="{{ $q(['canceladas' => $showCancelled ? null : 1]) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-muted hover:bg-surface-2 hover:text-ink">{{ $showCancelled ? 'Ocultar cancelados' : 'Mostrar cancelados' }}</a>
            </div>
        </div>

        @if ($waiting->isNotEmpty())
            <section aria-labelledby="fila-titulo" class="mb-6">
                <x-card class="border-warning/30 bg-warning-bg/40">
                    <h2 id="fila-titulo" class="mb-3 flex items-center gap-2 text-lg font-bold"><span class="flex size-9 items-center justify-center rounded-xl bg-warning-bg text-warning"><x-icon name="clock" :size="18" /></span> Fila de espera agora ({{ $waiting->count() }})</h2>
                    <ol class="divide-y divide-line">
                        @foreach ($waiting as $w)
                            @php
                                $mins = (int) $w->checked_in_at?->diffInMinutes(now());
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span><a href="{{ route('agenda.show', $w) }}" class="font-semibold text-link underline underline-offset-4">{{ $w->patient->name }}</a> <span class="text-sm text-muted">· {{ $w->professional?->user?->name ?? 'sem profissional' }}</span></span>
                                <span class="text-sm tabular-nums {{ $mins >= 30 ? 'font-semibold text-danger' : 'text-muted' }}">aguardando há {{ $mins }} min</span>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            </section>
        @endif

        <h2 class="mb-4 text-lg font-bold tracking-tight">{{ $title }} <span class="ml-1 align-middle text-sm font-semibold text-muted">· {{ $appointments->count() }} {{ $appointments->count() === 1 ? 'agendamento' : 'agendamentos' }}</span></h2>
        @if (! $week)
            @if ($appointments->isEmpty())
                <x-empty-state title="Nenhum agendamento neste dia" icon="clock">
                    @if ($fvCan('schedule:write'))<a class="font-semibold text-link underline underline-offset-4" href="{{ route('agenda.create', ['unidade' => $unit->id, 'data' => $day->format('Y-m-d')]) }}">Agendar</a>@endif
                </x-empty-state>
            @else
                <ul class="flex flex-col gap-3 sm:gap-2">
                    @foreach ($appointments as $a)
                        <li>@include('agenda._item', ['a' => $a, 'tz' => $tz])</li>
                    @endforeach
                </ul>
            @endif
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-7">
                @for ($i = 0; $i < 7; $i++)
                    @php
                        $d = $from->copy()->addDays($i);
                        $items = $byDay->get($d->format('Y-m-d'), collect());
                    @endphp
                    <section aria-label="{{ $d->locale('pt_BR')->translatedFormat('l, d/m') }}" class="flex min-w-0 flex-col gap-2">
                        <a href="{{ $q(['visao' => null, 'data' => $d->format('Y-m-d')]) }}" class="flex items-baseline justify-between rounded-xl px-3 py-2 text-sm font-bold {{ $d->isSameDay(now($tz)) ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'bg-surface shadow-card hover:bg-tint' }}">
                            <span>{{ ucfirst($d->locale('pt_BR')->translatedFormat('D d/m')) }}</span>
                            <span class="text-xs font-semibold opacity-80">{{ $items->count() }}</span>
                        </a>
                        @forelse ($items as $a)
                            @include('agenda._week-item', ['a' => $a, 'tz' => $tz])
                        @empty
                            <p class="rounded-xl border border-dashed border-line px-3 py-3 text-center text-xs text-muted">Livre</p>
                        @endforelse
                    </section>
                @endfor
            </div>
        @endif
    @endif
@endsection
