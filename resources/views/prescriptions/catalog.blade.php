@extends('layouts.app')
@section('title', 'Medicamentos — catálogo')

@section('content')
    <x-page-header title="Catálogo de medicamentos" icon="pill" eyebrow="Configurações" description="Concentração, dose usual por kg e faixa da clínica. Na receita, o sistema calcula a quantidade pelo peso e avisa quando a dose sai da faixa.">
        <a href="{{ route('medications.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo medicamento</a>
    </x-page-header>
    <x-alert tone="info" class="mb-6">Nada vem pré-cadastrado: doses e faixas são definidas pela clínica, conforme a literatura e a bula. O cálculo é uma conta para conferência; a decisão é sempre do médico-veterinário.</x-alert>
    @if ($medications->isEmpty())
        <x-empty-state title="Nenhum medicamento cadastrado" icon="pill">Cadastre os medicamentos que a clínica mais prescreve. Na receita também é possível digitar um medicamento fora do catálogo (sem cálculo pelo peso).</x-empty-state>
    @else
        <x-card class="p-1.5">
            <ul class="divide-y divide-line">
                @foreach ($medications as $m)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl px-3.5 py-3">
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2 font-semibold {{ $m->active ? '' : 'text-muted' }}">{{ $m->name }}
                                @if ($m->controlled)<x-badge tone="warning">Controle especial</x-badge>@endif
                                @unless ($m->active)<x-badge>Desativado</x-badge>@endunless
                            </span>
                            <span class="block text-sm text-muted">
                                {{ $m->presentation ?: $m->defaultPresentation() }}
                                @if ($m->dose_mg_kg !== null) · dose usual {{ \App\Models\Medication::num((float) $m->dose_mg_kg) }} mg/kg @endif
                                @if ($m->dose_min_mg_kg !== null || $m->dose_max_mg_kg !== null) · faixa {{ $m->dose_min_mg_kg !== null ? \App\Models\Medication::num((float) $m->dose_min_mg_kg) : '—' }} a {{ $m->dose_max_mg_kg !== null ? \App\Models\Medication::num((float) $m->dose_max_mg_kg) : '—' }} mg/kg @endif
                                @if ($m->route) · via {{ mb_strtolower($m->route) }} @endif
                                @if ($m->frequency) · {{ mb_strtolower($m->frequency) }} @endif
                                · {{ $m->species === [] ? 'todas as espécies' : collect($m->species)->map(fn ($s) => \App\Support\Species::label($s))->join(', ') }}
                            </span>
                        </span>
                        <a href="{{ route('medications.edit', $m) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-link underline underline-offset-4">Editar<span class="sr-only"> {{ $m->name }}</span></a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
