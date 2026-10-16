@extends('layouts.app')
@section('title', 'Pacientes')

@section('content')
    <x-page-header title="Pacientes" description="Animais atendidos por esta clínica, de todas as espécies.">
        @if ($fvCan('patients:write'))
            <a href="{{ route('patients.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-sm hover:brightness-110"><x-icon name="plus" /> Novo paciente</a>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('patients.index') }}" class="mb-5 grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" role="search">
        <x-field name="q" label="Buscar por paciente, responsável, telefone ou microchip" type="search" :value="$q" autocomplete="off" />
        <x-select name="especie" label="Espécie" :options="\App\Support\Species::LABELS" :value="$species" placeholder="Todas" />
        <x-button variant="secondary">Buscar</x-button>
        <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-3">
            <input type="checkbox" name="todos" value="1" class="size-4" @checked(request()->boolean('todos'))>
            <span>Incluir óbitos e arquivados</span>
        </label>
    </form>

    @if ($patients->isEmpty())
        <x-empty-state :title="$q === '' && $species === '' ? 'Nenhum paciente cadastrado' : 'Nenhum paciente encontrado'" icon="paw">
            @if ($q !== '') Tente parte do nome, do responsável ou os últimos dígitos do telefone. @endif
        </x-empty-state>
    @else
        <p class="mb-2 text-sm text-muted">{{ $patients->total() }} {{ $patients->total() === 1 ? 'paciente' : 'pacientes' }}</p>
        <ul class="flex flex-col gap-3">
            @foreach ($patients as $pt)
                <li>@include('patients._card', ['patient' => $pt, 'showGuardians' => true])</li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $patients->links('partials.pagination') }}</div>
    @endif
@endsection
