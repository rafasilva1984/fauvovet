@extends('layouts.base')

@php
    $nav = array_values(array_filter([
        ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'home', 'perm' => 'tenant:read'],
        ['route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'calendar', 'perm' => 'schedule:read'],
        ['route' => 'patients.index', 'label' => 'Pacientes', 'icon' => 'paw', 'perm' => 'patients:read'],
        ['route' => 'guardians.index', 'label' => 'Responsáveis', 'icon' => 'users', 'perm' => 'patients:read'],
        ['route' => 'units.index', 'label' => 'Unidades', 'icon' => 'building', 'perm' => 'units:read'],
        ['route' => 'team.index', 'label' => 'Equipe', 'icon' => 'shield', 'perm' => 'members:read'],
        ['route' => 'settings.appointment-types', 'label' => 'Tipos de atendimento', 'icon' => 'list', 'perm' => 'schedule:configure'],
        ['route' => 'settings.appearance', 'label' => 'Aparência', 'icon' => 'palette', 'perm' => 'theme:update'],
    ], fn ($n) => $fvCan($n['perm'])));
    $tenantName = $fvTenant?->trade_name ?? '';
    $unitLabel = $fvUnitName ?? 'Todas as unidades';
    $user = auth()->user();
    // Celular: até 4 itens na barra inferior; os demais ficam no menu da conta ("Mais").
    $navMobile = array_slice($nav, 0, 4);
    $navMore = array_slice($nav, 4);
    $navCols = ['', 'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4'][count($navMobile)];
@endphp

@section('body')
    <div class="min-h-dvh lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]" x-data="disclosure">
        {{-- Navegação lateral (desktop) --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-6 border-r border-line bg-surface px-4 py-5 lg:flex">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2">
                <x-logo :size="34" />
                <span class="text-lg font-semibold tracking-tight">FauvoVet</span>
            </a>
            @include('layouts.partials.context', ['tenantName' => $tenantName, 'unitLabel' => $unitLabel])
            <nav aria-label="Principal" class="flex flex-1 flex-col gap-1">
                @foreach ($nav as $item)
                    @php($active = request()->routeIs($item['route'], preg_replace('/\.index$/', '', $item['route']).'.*'))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                        class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition {{ $active ? 'bg-brand text-on-brand' : 'text-ink hover:bg-surface-2' }}">
                        <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-line pt-4">
                <p class="truncate px-2 text-sm font-medium">{{ $user->name }}</p>
                <p class="truncate px-2 text-xs text-muted">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <x-button variant="ghost" class="w-full justify-start"><x-icon name="logout" /> Sair</x-button>
                </form>
            </div>
        </aside>

        {{-- Barra superior (celular/tablet) --}}
        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-line bg-surface/95 px-4 py-2.5 backdrop-blur lg:hidden">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                <x-logo :size="30" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold leading-tight">{{ $tenantName }}</span>
                    <span class="block truncate text-xs text-muted">{{ $unitLabel }}</span>
                </span>
            </a>
            <button type="button" x-on:click="toggle" aria-label="Abrir menu da conta" aria-controls="menu-conta" x-bind:aria-expanded="open"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl hover:bg-surface-2">
                <x-icon name="menu" />
            </button>
        </header>

        <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Conta" id="menu-conta" x-on:keydown.escape.window="close">
            <button type="button" aria-label="Fechar menu" class="absolute inset-0 bg-black/40" x-on:click="close"></button>
            <div class="absolute inset-x-0 bottom-0 rounded-t-3xl border-t border-line bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-card">
                <div class="mb-4 flex items-center justify-between">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $user->name }}</p>
                        <p class="truncate text-sm text-muted">{{ $user->email }}</p>
                    </div>
                    <button type="button" x-on:click="close" aria-label="Fechar" class="flex size-11 items-center justify-center rounded-xl hover:bg-surface-2"><x-icon name="close" /></button>
                </div>
                @include('layouts.partials.context', ['tenantName' => $tenantName, 'unitLabel' => $unitLabel])
                @if ($navMore)
                    <nav aria-label="Mais opções" class="mt-4 grid grid-cols-2 gap-2">
                        @foreach ($navMore as $item)
                            <a href="{{ route($item['route']) }}" class="flex min-h-12 items-center gap-2 rounded-xl border border-line px-3 text-sm font-medium hover:bg-surface-2"><x-icon :name="$item['icon']" /> {{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <x-button variant="secondary" class="w-full"><x-icon name="logout" /> Sair</x-button>
                </form>
            </div>
        </div>

        <main id="conteudo" class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 lg:px-10 lg:pb-12 lg:pt-10">
            @if (session('status'))
                <x-alert tone="success" class="mb-6">{{ session('status') }}</x-alert>
            @endif
            @yield('content')
        </main>

        {{-- Navegação inferior (celular) --}}
        <nav aria-label="Principal" class="fixed inset-x-0 bottom-0 z-30 grid {{ $navCols }} border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
            @foreach ($navMobile as $item)
                @php($active = request()->routeIs($item['route'], preg_replace('/\.index$/', '', $item['route']).'.*'))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[0.7rem] font-medium {{ $active ? 'text-link' : 'text-muted' }}">
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
@endsection
