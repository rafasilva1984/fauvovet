@extends('layouts.app')
@section('title', 'Tipos de atendimento')

@push('styles')
    @include('agenda._type-colors', ['types' => $types])
@endpush

@section('content')
    <x-page-header title="Tipos de atendimento" description="Duração padrão e cor de cada tipo na agenda. Desative os que não usa; o histórico é mantido." />
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <x-card class="p-0">
            <ul class="divide-y divide-line">
                @foreach ($types as $t)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <span class="flex min-w-0 items-center gap-3">
                            <span aria-hidden="true" class="fv-at-dot-{{ strtolower($t->id) }} size-4 shrink-0 rounded-full"></span>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $t->name }}</span>
                                <span class="block text-sm text-muted">{{ $t->duration_minutes }} min · {{ $t->color }}{{ $t->active ? '' : ' · desativado' }}</span>
                            </span>
                        </span>
                        <a href="{{ route('settings.appointment-types.edit', $t) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-link underline underline-offset-4">Editar<span class="sr-only"> {{ $t->name }}</span></a>
                    </li>
                @endforeach
            </ul>
        </x-card>
        <section aria-labelledby="novo-tipo">
            <x-card>
                <h2 id="novo-tipo" class="mb-4 text-lg font-semibold">Novo tipo</h2>
                <form method="POST" action="{{ route('settings.appointment-types.store') }}" class="flex flex-col gap-4" novalidate>
                    @csrf
                    <x-field name="name" label="Nome" required />
                    <x-field name="duration_minutes" label="Duração (minutos)" type="number" min="5" max="480" step="5" value="30" required />
                    <x-field name="color" label="Cor (#RRGGBB)" value="#0e5e66" maxlength="7" required hint="Cor escura o bastante para aparecer sobre o branco (contraste mínimo 3:1)." />
                    <x-button>Criar tipo</x-button>
                </form>
            </x-card>
        </section>
    </div>
@endsection
