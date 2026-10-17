@extends('layouts.app')
@section('title', 'Editar '.$type->name)

@section('content')
    <x-page-header :title="'Editar '.$type->name" icon="list" eyebrow="Tipos de atendimento" />
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('settings.appointment-types.update', $type) }}" class="flex flex-col gap-4" novalidate>
            @csrf
            @method('PUT')
            <x-field name="name" label="Nome" :value="$type->name" required />
            <x-field name="duration_minutes" label="Duração (minutos)" type="number" min="5" max="480" step="5" :value="$type->duration_minutes" required />
            <x-field name="color" label="Cor na agenda" type="color" :value="$type->color" required class="max-w-40" hint="Escolha uma cor escura o bastante para aparecer sobre o branco." />
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" class="size-4" @checked(old('active', $type->active))>
                <span>Ativo (aparece ao agendar)</span>
            </label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar</x-button>
                <a href="{{ route('settings.appointment-types') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Voltar</a>
            </div>
        </form>
    </x-card>
@endsection
