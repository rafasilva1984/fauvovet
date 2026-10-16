@extends('layouts.app')
@section('title', 'Editar '.$patient->name)

@section('content')
    <x-page-header :title="'Editar '.$patient->name" icon="paw" eyebrow="Paciente" />
    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('patients.update', $patient) }}" class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PUT')
            @if ($errors->any())<x-alert>Revise os campos destacados.</x-alert>@endif
            @include('patients._fields', ['patient' => $patient])
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="status" label="Situação" :options="\App\Models\Patient::STATUS" :value="$patient->status" />
                <x-field name="deceased_on" label="Data do óbito" type="date" :value="$patient->deceased_on?->format('Y-m-d')" hint="Obrigatória quando a situação for Óbito." />
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar alterações</x-button>
                <a href="{{ route('patients.show', $patient) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
