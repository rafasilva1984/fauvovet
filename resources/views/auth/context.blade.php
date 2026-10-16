@extends('layouts.auth')
@section('title', 'Escolha onde trabalhar')
@section('heading', 'Escolha onde trabalhar')
@section('subtitle', 'Cada clínica é um ambiente separado. Você verá apenas os dados da clínica selecionada.')

@section('content')
    <div class="flex flex-col gap-4">
        @if (session('status'))
            <x-alert tone="warning">{{ session('status') }}</x-alert>
        @endif
        @forelse ($memberships as $m)
            <form method="POST" action="{{ route('context.store') }}" class="fv-lift rounded-2xl border border-line bg-surface p-4 shadow-card">
                @csrf
                <input type="hidden" name="membership_id" value="{{ $m['membership_id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex min-w-0 items-start gap-3">
                    <x-avatar :name="$m['tenant_name']" />
                    <div class="min-w-0">
                        <h2 class="font-bold text-ink">{{ $m['tenant_name'] }}</h2>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($m['role_labels'] as $label)
                                <x-badge tone="tint">{{ $label }}</x-badge>
                            @endforeach
                        </div>
                    </div>
                    </div>
                    @if ($current === $m['membership_id'])
                        <x-badge tone="brand">Atual</x-badge>
                    @endif
                </div>
                @if (count($m['units']) > 1)
                    <fieldset class="mt-4">
                        <legend class="mb-2 text-sm font-medium">Unidade</legend>
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            @foreach ($m['units'] as $i => $u)
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line px-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-surface-2">
                                    <input type="radio" name="unit_id" value="{{ $u['id'] }}" @checked($i === 0) class="accent-brand">
                                    {{ $u['name'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <x-button class="mt-4 w-full sm:w-auto">Entrar em {{ $m['tenant_name'] }}</x-button>
            </form>
        @empty
            <x-alert tone="warning">Seu usuário não possui vínculo ativo com nenhuma clínica. Peça um convite ao responsável.</x-alert>
        @endforelse
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="ghost">Sair</x-button>
        </form>
    </div>
@endsection
