@extends('layouts.app')
@section('title', 'Novo responsável')

@section('content')
    <x-page-header title="Novo responsável" description="Depois de salvar, você pode cadastrar os pacientes deste responsável." />
    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('guardians.store') }}" class="flex flex-col gap-6" novalidate>
            @csrf
            @include('guardians._duplicates')
            @if ($errors->any())<x-alert>Revise os campos destacados.</x-alert>@endif
            @include('guardians._fields', ['guardian' => null])
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar responsável</x-button>
                <a href="{{ route('guardians.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line px-4 text-sm font-semibold hover:bg-surface-2">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
