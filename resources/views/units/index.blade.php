@extends('layouts.app')
@section('title', 'Unidades')

@section('content')
    <x-page-header title="Unidades" icon="building" eyebrow="Clínica" description="Estabelecimentos ou locais de atendimento da clínica. Cada unidade tem seu próprio fuso horário." />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section aria-label="Lista de unidades">
            @if ($units->isEmpty())
                <x-empty-state title="Nenhuma unidade cadastrada" icon="building" />
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($units as $unit)
                        <li>
                            <a href="{{ route('units.show', $unit) }}">
                                <x-card :hover="true" class="flex items-center gap-4">
                                    <x-avatar :name="$unit->name" icon="building" />
                                    <span class="min-w-0">
                                        <span class="block font-bold text-ink">{{ $unit->name }}</span>
                                        <span class="block text-sm text-muted">{{ collect([$unit->city, $unit->state])->filter()->join(' · ') ?: 'Endereço não informado' }} · fuso {{ $unit->timezone }}</span>
                                    </span>
                                    <x-icon name="chevron-right" :size="18" class="ml-auto shrink-0 text-muted" />
                                </x-card>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($fvCan('units:create'))
            <section aria-labelledby="nova-titulo" id="nova">
                <x-card>
                    <h2 id="nova-titulo" class="mb-4 text-lg font-bold tracking-tight">Nova unidade</h2>
                    <form method="POST" action="{{ route('units.store') }}" class="flex flex-col gap-4" novalidate>
                        @csrf
                        <x-field name="name" label="Nome" required />
                        <div class="grid grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] gap-3">
                            <x-field name="city" label="Cidade" />
                            <x-select name="state" label="UF" :options="\App\Support\BrazilianStates::options()" placeholder="Selecione" />
                        </div>
                        <x-button>Criar unidade</x-button>
                    </form>
                </x-card>
            </section>
        @endif
    </div>
@endsection
