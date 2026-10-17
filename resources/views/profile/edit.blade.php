@extends('layouts.app')
@section('title', 'Meu perfil')

@section('content')
    <x-profile-header :title="$user->name" eyebrow="Meu perfil" :description="$user->email" />
    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('profile.update') }}" class="flex flex-col gap-4" novalidate>
            @csrf
            @method('PUT')
            <x-field name="name" label="Nome completo" :value="$user->name" required autocomplete="name" />
            <x-field name="crmv" label="CRMV" :value="$user->crmv" placeholder="CRMV-SP 12345" hint="Aparece nas receitas, atestados e encaminhamentos que você emitir. Obrigatório para esses documentos." />
            <x-button class="self-start">Salvar</x-button>
        </form>
    </x-card>
@endsection
