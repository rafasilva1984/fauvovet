@extends('layouts.app')
@section('title', 'Responsáveis')

@section('content')
    <x-page-header title="Responsáveis" icon="users" eyebrow="Cadastro" description="Tutores e responsáveis pelos pacientes desta clínica.">
        @if ($fvCan('patients:write'))
            <a href="{{ route('guardians.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo responsável</a>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('guardians.index') }}" class="mb-6 flex flex-col gap-3 rounded-[1.25rem] border border-line bg-surface p-4 shadow-card sm:flex-row sm:items-end" role="search">
        <x-field name="q" label="Buscar por nome, telefone, e-mail ou CPF" type="search" :value="$q" class="flex-1" autocomplete="off" />
        <x-button variant="secondary">Buscar</x-button>
    </form>

    @if ($guardians->isEmpty())
        <x-empty-state :title="$q === '' ? 'Nenhum responsável cadastrado' : 'Nenhum responsável encontrado'" icon="users">
            @if ($q !== '') Tente outro termo, ou só os últimos dígitos do telefone. @endif
        </x-empty-state>
    @else
        <p class="mb-3 text-sm font-semibold text-muted">{{ $guardians->total() }} {{ $guardians->total() === 1 ? 'responsável' : 'responsáveis' }}</p>
        <x-card class="p-1.5">
            <ul class="divide-y divide-line">
                @foreach ($guardians as $g)
                    <li>
                        <a href="{{ route('guardians.show', $g) }}" class="group flex items-center gap-4 rounded-2xl px-3.5 py-3 transition hover:bg-tint">
                            <x-avatar :name="$g->name" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-ink">{{ $g->name }}</span>
                                <span class="block truncate text-sm text-muted">{{ collect([\App\Models\Guardian::formatPhone($g->phone), $g->email])->filter()->join(' · ') }}</span>
                            </span>
                            <span class="hidden gap-1.5 sm:flex">
                                <x-badge tone="tint">{{ $g->patients_count }} {{ $g->patients_count === 1 ? 'paciente' : 'pacientes' }}</x-badge>
                                @if ($g->status === 'archived')<x-badge tone="warning">Arquivado</x-badge>@endif
                            </span>
                            @if ($g->status === 'archived')<span class="sm:hidden"><x-badge tone="warning">Arquivado</x-badge></span>@endif
                            <x-icon name="chevron-right" :size="18" class="shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-link" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
        <div class="mt-4">{{ $guardians->links('partials.pagination') }}</div>
    @endif
@endsection
