@extends('layouts.app')
@section('title', 'Novo paciente')

@section('content')
    <x-page-header title="Novo paciente" icon="paw" eyebrow="Cadastro" :description="$guardian ? 'Responsável: '.$guardian->name : 'Cadastro rápido: paciente e responsável na mesma tela.'" />
    <form method="POST" action="{{ route('patients.store') }}" class="flex max-w-3xl flex-col gap-6" novalidate>
        @csrf
        @include('guardians._duplicates')
        @if ($errors->any())<x-alert>Revise os campos destacados.</x-alert>@endif

        <x-card>
            <h2 class="mb-4 text-lg font-bold tracking-tight">Paciente</h2>
            @include('patients._fields', ['patient' => $patient])
        </x-card>

        <x-card>
            <h2 class="mb-4 text-lg font-bold tracking-tight">Responsável</h2>
            @if ($guardian)
                <input type="hidden" name="guardian_id" value="{{ $guardian->id }}">
                <p class="mb-4"><strong>{{ $guardian->name }}</strong> <span class="text-muted">{{ \App\Models\Guardian::formatPhone($guardian->phone) }}</span></p>
            @else
                <p class="mb-4 text-sm text-muted">Responsável já cadastrado? Abra-o em <a class="font-semibold text-link underline underline-offset-4" href="{{ route('guardians.index') }}">Responsáveis</a> e use “Novo paciente”. Os demais dados de contato podem ser completados depois.</p>
                @include('guardians._fields', ['guardian' => null, 'p' => 'g_', 'full' => false])
            @endif
            <x-select name="relationship" label="Vínculo" :options="\App\Models\Patient::RELATIONSHIPS" value="primary" class="mt-4 sm:max-w-sm" />
        </x-card>

        <div class="flex flex-col gap-3 sm:flex-row">
            <x-button>Salvar paciente</x-button>
            <a href="{{ $guardian ? route('guardians.show', $guardian) : route('patients.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Cancelar</a>
        </div>
    </form>
@endsection
