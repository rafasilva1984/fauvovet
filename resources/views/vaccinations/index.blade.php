@extends('layouts.app')
@section('title', 'Vacinas a vencer')

@section('content')
    <x-page-header title="Vacinas a vencer" icon="syringe" eyebrow="Atendimento" description="Atrasadas e próximas doses dos pacientes ativos — para avisar os tutores.">
        <nav aria-label="Período" class="flex rounded-2xl border border-line bg-surface p-1 shadow-card">
            @foreach ([7, 30, 60, 90] as $d)
                <a href="{{ route('vaccinations.index', ['dias' => $d]) }}" @if ($d === $days) aria-current="page" @endif class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold {{ $d === $days ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'hover:bg-surface-2' }}">{{ $d }} dias</a>
            @endforeach
        </nav>
    </x-page-header>
    @if ($due->isEmpty())
        <x-empty-state title="Nenhuma vacina atrasada ou a vencer no período" icon="syringe" />
    @else
        <x-card class="p-1.5">
            <ul class="divide-y divide-line">
                @foreach ($due as $v)
                    @php
                        $daysLeft = (int) \Illuminate\Support\Carbon::today()->diffInDays($v->next_due_on, false);
                        $g = $v->patient->guardians->first();
                    @endphp
                    <li>
                        <a href="{{ route('patients.show', $v->patient) }}#vacinas" class="group flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl px-3.5 py-3 transition hover:bg-tint">
                            <x-avatar :name="$v->patient->name" icon="paw" :tone="\App\Support\Species::tone($v->patient->species)" />
                            <span class="min-w-0 flex-1 basis-48">
                                <span class="block font-semibold">{{ $v->patient->name }} · {{ $v->vaccine->name }}</span>
                                <span class="block truncate text-sm text-muted">Dose {{ $v->dose_number + 1 }} prevista para {{ $v->next_due_on->format('d/m/Y') }}{{ $g ? ' · '.$g->name.($g->phone ? ' '.\App\Models\Guardian::formatPhone($g->phone) : '') : '' }}</span>
                            </span>
                            @include('vaccinations._status', ['status' => $daysLeft < 0 ? 'overdue' : 'soon', 'days' => $daysLeft])
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
