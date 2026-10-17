@extends('layouts.app')
@section('title', 'Vacinas — catálogo')

@section('content')
    <x-page-header title="Catálogo de vacinas" icon="syringe" eyebrow="Configurações" description="Protocolos da clínica: doses da série, intervalo e reforço. A próxima dose é calculada a partir daqui.">
        <a href="{{ route('vaccines.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Nova vacina</a>
    </x-page-header>
    <x-alert tone="info" class="mb-6">Os modelos iniciais são genéricos. Revise doses e intervalos com o protocolo da sua clínica e a bula de cada fabricante.</x-alert>
    <x-card class="p-1.5">
        <ul class="divide-y divide-line">
            @foreach ($vaccines as $v)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl px-3.5 py-3">
                    <span class="min-w-0">
                        <span class="block font-semibold {{ $v->active ? '' : 'text-muted' }}">{{ $v->name }}</span>
                        <span class="block text-sm text-muted">
                            {{ $v->species === [] ? 'Todas as espécies' : collect($v->species)->map(fn ($s) => \App\Support\Species::label($s))->join(', ') }}
                            · {{ $v->series_doses }} {{ $v->series_doses === 1 ? 'dose' : 'doses' }}{{ $v->series_doses > 1 ? ' a cada '.$v->series_interval_days.' dias' : '' }}
                            · {{ $v->booster_interval_days ? 'reforço a cada '.$v->booster_interval_days.' dias' : 'sem reforço' }}{{ $v->active ? '' : ' · desativada' }}
                        </span>
                    </span>
                    <a href="{{ route('vaccines.edit', $v) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-link underline underline-offset-4">Editar<span class="sr-only"> {{ $v->name }}</span></a>
                </li>
            @endforeach
        </ul>
    </x-card>
@endsection
