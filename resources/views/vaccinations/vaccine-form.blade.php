@extends('layouts.app')
@section('title', $vaccine->exists ? 'Editar '.$vaccine->name : 'Nova vacina')

@section('content')
    <x-page-header icon="syringe" eyebrow="Catálogo de vacinas" :title="$vaccine->exists ? 'Editar '.$vaccine->name : 'Nova vacina'" />
    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $vaccine->exists ? route('vaccines.update', $vaccine) : route('vaccines.store') }}" class="flex flex-col gap-5" novalidate>
            @csrf
            @if ($vaccine->exists) @method('PUT') @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nome" :value="$vaccine->name" required />
                <x-field name="manufacturer" label="Fabricante padrão" :value="$vaccine->manufacturer" />
            </div>
            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Espécies (nenhuma marcada = todas)</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Support\Species::LABELS as $code => $label)
                        <label class="flex min-h-11 items-center gap-2 rounded-xl border border-line px-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-tint">
                            <input type="checkbox" name="species[]" value="{{ $code }}" class="size-4" @checked(in_array($code, old('species', $vaccine->species ?? []), true))> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field name="series_doses" label="Doses na série" type="number" min="1" max="10" :value="$vaccine->series_doses" required />
                <x-field name="series_interval_days" label="Intervalo entre doses (dias)" type="number" min="0" max="365" :value="$vaccine->series_interval_days" required />
                <x-field name="booster_interval_days" label="Reforço a cada (dias)" type="number" min="1" max="3650" :value="$vaccine->booster_interval_days" hint="Vazio = sem reforço." />
            </div>
            <x-textarea name="notes" label="Observações" :value="$vaccine->notes" rows="2" />
            @if ($vaccine->exists)
                <label class="flex min-h-11 items-center gap-2 text-sm">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="size-4" @checked(old('active', $vaccine->active))>
                    <span>Ativa (aparece ao registrar aplicações)</span>
                </label>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar</x-button>
                <a href="{{ route('vaccines.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Voltar</a>
            </div>
        </form>
    </x-card>
@endsection
