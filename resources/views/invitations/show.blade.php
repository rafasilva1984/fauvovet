@extends('layouts.auth')
@section('title', 'Convite para a equipe')
@section('width', 'max-w-lg')
@section('heading', 'Convite para '.$tenantName)
@section('subtitle', 'Você foi convidado(a) como '.collect($invitation->roleList())->map(fn ($r) => \App\Tenancy\Roles::label($r))->join(', ').'.')

@section('content')
    @if ($errors->has('invitation'))
        <x-alert class="mb-6">{{ $errors->first('invitation') }}</x-alert>
    @endif

    @switch($state)
        @case('wrong_account')
            <x-alert tone="warning">Este convite foi enviado para outro e-mail. Saia da sua conta e abra o link de novo para entrar com a conta certa ou criar uma.</x-alert>
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <x-button variant="secondary">Sair da conta atual</x-button>
            </form>
        @break

        @case('confirm')
            <p class="text-muted">Você vai entrar na equipe com a conta <strong class="text-ink">{{ $invitation->email }}</strong>.</p>
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="mt-6">
                @csrf
                <x-button class="w-full sm:w-auto">Aceitar convite</x-button>
            </form>
        @break

        @case('login')
            <p class="text-muted">Já existe uma conta no FauvoVet para <strong class="text-ink">{{ $invitation->email }}</strong>. Entre com ela; depois do login você volta para esta página para aceitar.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand shadow-sm hover:brightness-110">Entrar para aceitar</a>
        @break

        @default
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @if ($errors->any() && ! $errors->has('invitation'))
                    <x-alert>Revise os campos destacados.</x-alert>
                @endif
                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-medium text-ink">E-mail de acesso</span>
                    <p class="min-h-11 rounded-xl border border-line bg-surface-2 px-3.5 py-2.5 text-ink">{{ $invitation->email }}</p>
                </div>
                <x-field name="name" label="Seu nome" autocomplete="name" required />
                <x-field name="crmv" label="CRMV (opcional)" hint="Ex.: CRMV-SP 00000" />
                <x-field name="password" label="Crie uma senha" type="password" autocomplete="new-password" required hint="Mínimo de 12 caracteres." />
                <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
                <x-button class="w-full sm:w-auto sm:self-start">Criar conta e entrar na equipe</x-button>
            </form>
    @endswitch
@endsection
