@extends('layouts.app')
@section('title', 'Responsáveis')

@section('content')
    <x-page-header title="Responsáveis" description="Tutores e responsáveis pelos pacientes desta clínica.">
        @if ($fvCan('patients:write'))
            <a href="{{ route('guardians.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-sm hover:brightness-110"><x-icon name="plus" /> Novo responsável</a>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('guardians.index') }}" class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end" role="search">
        <x-field name="q" label="Buscar por nome, telefone, e-mail ou CPF" type="search" :value="$q" class="flex-1" autocomplete="off" />
        <x-button variant="secondary">Buscar</x-button>
    </form>

    @if ($guardians->isEmpty())
        <x-empty-state :title="$q === '' ? 'Nenhum responsável cadastrado' : 'Nenhum responsável encontrado'" icon="users">
            @if ($q !== '') Tente outro termo, ou só os últimos dígitos do telefone. @endif
        </x-empty-state>
    @else
        <p class="mb-2 text-sm text-muted">{{ $guardians->total() }} {{ $guardians->total() === 1 ? 'responsável' : 'responsáveis' }}</p>
        <x-card class="p-0">
            <ul class="divide-y divide-line">
                @foreach ($guardians as $g)
                    <li>
                        <a href="{{ route('guardians.show', $g) }}" class="flex flex-col gap-1 px-5 py-4 hover:bg-surface-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $g->name }}</span>
                                <span class="block truncate text-sm text-muted">{{ collect([\App\Models\Guardian::formatPhone($g->phone), $g->email])->filter()->join(' · ') }}</span>
                            </span>
                            <span class="flex gap-1.5">
                                <x-badge>{{ $g->patients_count }} {{ $g->patients_count === 1 ? 'paciente' : 'pacientes' }}</x-badge>
                                @if ($g->status === 'archived')<x-badge>Arquivado</x-badge>@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
        <div class="mt-4">{{ $guardians->links('partials.pagination') }}</div>
    @endif
@endsection
