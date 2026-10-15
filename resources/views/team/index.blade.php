@extends('layouts.app')
@section('title', 'Equipe')

@section('content')
    <x-page-header title="Equipe" description="Pessoas com acesso a esta clínica e seus papéis. Convites e edição de papéis chegam na Fase 1." />
    @if ($members->isEmpty())
        <x-empty-state title="Nenhum membro" icon="users" />
    @else
        <x-card class="p-0">
            <ul class="divide-y divide-line">
                @foreach ($members as $m)
                    <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $m->user->name }}</p>
                            <p class="truncate text-sm text-muted">{{ $m->user->email }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($m->roleList() as $role)
                                <x-badge>{{ \App\Tenancy\Roles::label($role) }}</x-badge>
                            @endforeach
                            @if ($m->status !== 'active')
                                <x-badge>{{ $m->status === 'disabled' ? 'Desativado' : 'Convidado' }}</x-badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
