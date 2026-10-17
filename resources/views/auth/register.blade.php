@extends('layouts.auth')
@section('title', 'Cadastre sua clínica')
@section('width', 'max-w-2xl')
@section('heading', 'Cadastre sua clínica')
@section('subtitle', 'Crie o ambiente isolado da sua clínica ou do seu atendimento autônomo.')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" novalidate x-data="personType" data-initial="{{ old('person_type', 'PJ') }}">
        @csrf
        @if ($errors->any())
            <x-alert>Revise os campos destacados.</x-alert>
        @endif
        <fieldset>
            <legend class="mb-2 text-sm font-medium">Tipo de cadastro</legend>
            <div class="grid grid-cols-2 gap-2 rounded-2xl bg-surface-2 p-1">
                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-xl px-3 text-center text-sm font-semibold transition has-[:checked]:bg-surface has-[:checked]:text-ink has-[:checked]:shadow-card text-muted has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                    <input type="radio" name="person_type" value="PJ" class="sr-only" x-on:change="setPj" @checked(old('person_type', 'PJ') === 'PJ')>
                    Clínica / hospital (CNPJ)
                </label>
                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-xl px-3 text-center text-sm font-semibold transition has-[:checked]:bg-surface has-[:checked]:text-ink has-[:checked]:shadow-card text-muted has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                    <input type="radio" name="person_type" value="PF" class="sr-only" x-on:change="setPf" @checked(old('person_type') === 'PF')>
                    Profissional autônomo (CPF)
                </label>
            </div>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="trade_name" label="Nome da clínica ou nome profissional" required class="sm:col-span-2" />
            <div x-show="isPj" class="sm:col-span-1"><x-field name="legal_name" label="Razão social" /></div>
            <x-field name="document" label="CPF ou CNPJ" inputmode="numeric" hint="CPF para autônomo; CNPJ para clínica. Validamos só os dígitos verificadores." />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="owner_name" label="Seu nome" autocomplete="name" required />
            <x-field name="owner_crmv" label="CRMV" hint="Ex.: CRMV-SP 00000" />
            <x-field name="email" label="E-mail de acesso" type="email" autocomplete="email" inputmode="email" required class="sm:col-span-2" />
            <x-field name="password" label="Senha" type="password" autocomplete="new-password" required hint="Mínimo de 12 caracteres." />
            <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
            <x-field name="unit_name" label="Nome da primeira unidade" placeholder="Matriz" class="sm:col-span-2" />
        </div>

        <x-button class="w-full sm:w-auto sm:self-start">Criar ambiente</x-button>
    </form>
    <p class="mt-8 text-sm text-muted">Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-link underline underline-offset-4">Entrar</a></p>
@endsection
