@extends('layouts.auth')
@section('title', 'Entrar')
@section('heading', 'Entrar')
@section('subtitle', 'Acesse o ambiente da sua clínica.')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" novalidate>
        @csrf
        @if (session('status'))
            <x-alert tone="info">{{ session('status') }}</x-alert>
        @endif
        <x-field name="email" label="E-mail" type="email" autocomplete="username" inputmode="email" required autofocus />
        <div x-data="passwordField">
            <x-field name="password" label="Senha" type="password" autocomplete="current-password" required x-bind:type="type">
                <x-slot:trailing>
                    <button type="button" x-on:click="toggle" x-bind:aria-label="label" x-bind:aria-pressed="visible" aria-label="Mostrar senha"
                        class="flex size-10 items-center justify-center rounded-lg text-muted hover:text-ink">
                        <x-icon name="eye" />
                    </button>
                </x-slot:trailing>
            </x-field>
        </div>
        <x-button class="w-full">Entrar</x-button>
    </form>
    @if (config('fauvovet.allow_self_signup'))
        <p class="mt-8 text-sm text-muted">Ainda não usa o FauvoVet?
            <a href="{{ route('register') }}" class="font-semibold text-link underline underline-offset-4">Cadastre sua clínica</a></p>
    @endif
    <p class="mt-3 text-xs text-muted">Recuperação de senha por e-mail ainda não está disponível nesta versão.</p>
@endsection
