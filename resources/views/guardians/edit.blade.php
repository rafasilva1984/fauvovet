@extends('layouts.app')
@section('title', 'Editar '.$guardian->name)

@section('content')
    <x-page-header :title="'Editar '.$guardian->name" />
    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('guardians.update', $guardian) }}" class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PUT')
            @include('guardians._duplicates')
            @if ($errors->any())<x-alert>Revise os campos destacados.</x-alert>@endif
            @include('guardians._fields', ['guardian' => $guardian])
            <x-select name="status" label="Situação" :options="['active' => 'Ativo', 'archived' => 'Arquivado (não aparece nas buscas)']" :value="$guardian->status" class="sm:max-w-sm" />
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar alterações</x-button>
                <a href="{{ route('guardians.show', $guardian) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-surface-2">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
