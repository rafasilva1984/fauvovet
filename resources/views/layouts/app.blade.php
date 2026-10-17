@extends('layouts.base')

@php
    $groups = [
        'Atendimento' => [
            ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'home', 'perm' => 'tenant:read'],
            ['route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'calendar', 'perm' => 'schedule:read'],
            ['route' => 'patients.index', 'label' => 'Pacientes', 'icon' => 'paw', 'perm' => 'patients:read'],
            ['route' => 'guardians.index', 'label' => 'Responsáveis', 'icon' => 'users', 'perm' => 'patients:read'],
            ['route' => 'vaccinations.index', 'label' => 'Vacinas a vencer', 'icon' => 'syringe', 'perm' => 'vaccines:read'],
        ],
        'Clínica' => [
            ['route' => 'units.index', 'label' => 'Unidades', 'icon' => 'building', 'perm' => 'units:read'],
            ['route' => 'team.index', 'label' => 'Equipe', 'icon' => 'shield', 'perm' => 'members:read'],
        ],
        'Configurações' => [
            ['route' => 'settings.appointment-types', 'label' => 'Tipos de atendimento', 'icon' => 'list', 'perm' => 'schedule:configure'],
            ['route' => 'vaccines.index', 'label' => 'Catálogo de vacinas', 'icon' => 'syringe', 'perm' => 'vaccines:configure'],
            ['route' => 'document-templates.index', 'label' => 'Modelos de documento', 'icon' => 'copy', 'perm' => 'documents:templates'],
            ['route' => 'settings.appearance', 'label' => 'Aparência', 'icon' => 'palette', 'perm' => 'theme:update'],
        ],
    ];
    foreach ($groups as $g => $items) {
        $groups[$g] = array_values(array_filter($items, fn ($n) => $fvCan($n['perm'])));
    }
    $groups = array_filter($groups);
    $nav = $groups ? array_merge(...array_values($groups)) : [];
    $isActive = fn (array $item) => request()->routeIs($item['route'], preg_replace('/\.index$/', '', $item['route']).'.*');
    $tenantName = $fvTenant?->trade_name ?? '';
    $unitLabel = $fvUnitName ?? 'Todas as unidades';
    $user = auth()->user();
    // Celular: até 4 itens na barra; os demais ficam no menu da conta ("Mais opções").
    $navMobile = array_slice($nav, 0, 4);
    $navMore = array_slice($nav, 4);
    $navCols = ['', 'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4'][count($navMobile)];
    $quick = array_values(array_filter([
        $fvCan('schedule:write') ? ['agenda.create', 'Novo agendamento', 'calendar'] : null,
        $fvCan('patients:write') ? ['patients.create', 'Novo paciente', 'paw'] : null,
        $fvCan('patients:write') ? ['guardians.create', 'Novo responsável', 'user'] : null,
    ]));
@endphp

@section('body')
    <div class="fv-mesh min-h-dvh lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]" x-data="disclosure">
        {{-- Navegação lateral (desktop): painel escuro fixo, destaque na cor da clínica --}}
        <aside class="sticky top-0 hidden h-dvh flex-col gap-5 bg-side px-4 py-5 text-side-text lg:flex">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2">
                <x-logo :size="38" />
                <span>
                    <span class="block text-lg font-bold leading-tight tracking-tight">FauvoVet</span>
                    <span class="block text-[0.7rem] font-medium uppercase tracking-[0.16em] text-side-muted">Gestão veterinária</span>
                </span>
            </a>

            <div class="rounded-2xl bg-side-2 p-3 ring-1 ring-white/5">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$tenantName" size="sm" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" title="{{ $tenantName }}">{{ $tenantName }}</p>
                        <p class="truncate text-xs text-side-muted">{{ $unitLabel }}</p>
                    </div>
                </div>
                <a href="{{ route('context.show') }}" class="mt-2.5 inline-flex min-h-8 items-center gap-1.5 rounded-lg px-1 text-xs font-semibold text-side-text hover:text-white">
                    <x-icon name="swap" :size="16" /> Trocar clínica/unidade
                </a>
            </div>

            <nav aria-label="Principal" class="-mx-1 flex flex-1 flex-col gap-5 overflow-y-auto px-1">
                @foreach ($groups as $groupLabel => $items)
                    <div>
                        <p class="mb-1.5 px-3 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-side-muted">{{ $groupLabel }}</p>
                        <div class="flex flex-col gap-0.5">
                            @foreach ($items as $item)
                                @php($active = $isActive($item))
                                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition {{ $active ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'text-side-muted hover:bg-white/5 hover:text-side-text' }}">
                                    <x-icon :name="$item['icon']" class="{{ $active ? '' : 'transition group-hover:scale-110' }}" /> {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="flex items-center gap-3 rounded-2xl bg-side-2 p-3 ring-1 ring-white/5">
                <x-avatar :name="$user->name" size="sm" />
                <a href="{{ route('profile.edit') }}" class="min-w-0 flex-1 rounded-lg hover:text-white" title="Meu perfil">
                    <p class="truncate text-sm font-semibold">{{ $user->name }}</p>
                    <p class="truncate text-xs text-side-muted">Meu perfil · {{ $user->email }}</p>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex size-10 items-center justify-center rounded-xl text-side-muted transition hover:bg-white/10 hover:text-white" title="Sair">
                        <x-icon name="logout" :size="18" /><span class="sr-only">Sair</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            {{-- Barra superior (desktop): busca rápida + criação rápida --}}
            <header class="fv-glass sticky top-0 z-30 hidden items-center gap-4 border-b border-line/70 px-10 py-3 lg:flex">
                @if ($fvCan('patients:read'))
                    <form method="GET" action="{{ route('patients.index') }}" role="search" class="relative w-full max-w-md">
                        <label for="busca-rapida" class="sr-only">Busca rápida</label>
                        <x-icon name="search" :size="18" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" />
                        <input id="busca-rapida" name="q" type="search" autocomplete="off" placeholder="Paciente, tutor, telefone ou microchip…"
                            class="min-h-11 w-full rounded-xl border border-line bg-surface pl-10 pr-24 text-sm text-ink placeholder:text-muted transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-4 focus:ring-ring/25">
                        <button type="submit" aria-label="Pesquisar" class="absolute right-1.5 top-1/2 inline-flex min-h-8 -translate-y-1/2 items-center rounded-lg bg-surface-2 px-3 text-xs font-semibold text-muted hover:text-ink">Enter ↵</button>
                    </form>
                @endif
                <div class="ml-auto flex items-center gap-3">
                    <p class="hidden text-sm text-muted xl:block">{{ ucfirst(now('America/Sao_Paulo')->locale('pt_BR')->translatedFormat('l, d \d\e F')) }}</p>
                    @if ($quick)
                        <div class="relative" x-data="disclosure" x-on:keydown.escape="close" x-on:click.outside="close">
                            <x-button type="button" x-on:click="toggle" x-bind:aria-expanded="open" aria-haspopup="true" aria-controls="menu-novo">
                                <x-icon name="plus" :size="18" /> Novo <x-icon name="chevron-down" :size="16" />
                            </x-button>
                            <div id="menu-novo" x-show="open" x-cloak class="absolute right-0 top-full z-40 mt-2 w-60 animate-fade-up rounded-2xl border border-line bg-surface p-1.5 shadow-lift">
                                @foreach ($quick as [$route, $label, $icon])
                                    <a href="{{ route($route) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-ink hover:bg-tint">
                                        <span class="flex size-8 items-center justify-center rounded-lg bg-tint text-link"><x-icon :name="$icon" :size="16" /></span> {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            {{-- Barra superior (celular/tablet) --}}
            <header class="fv-glass sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-line/70 px-4 py-2.5 lg:hidden">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <x-logo :size="34" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-bold leading-tight">{{ $tenantName }}</span>
                        <span class="block truncate text-xs text-muted">{{ $unitLabel }}</span>
                    </span>
                </a>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($fvCan('patients:read'))
                        <a href="{{ route('patients.index') }}" aria-label="Buscar pacientes" class="flex size-11 items-center justify-center rounded-xl text-ink hover:bg-surface-2"><x-icon name="search" /></a>
                    @endif
                    <button type="button" x-on:click="toggle" aria-label="Abrir menu da conta" aria-controls="menu-conta" x-bind:aria-expanded="open"
                        class="flex size-11 items-center justify-center rounded-xl hover:bg-surface-2">
                        <x-avatar :name="$user->name" size="sm" />
                    </button>
                </div>
            </header>

            <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Conta" id="menu-conta" x-on:keydown.escape.window="close">
                <button type="button" aria-label="Fechar menu" class="absolute inset-0 bg-black/45 backdrop-blur-[2px]" x-on:click="close"></button>
                <div class="absolute inset-x-0 bottom-0 max-h-[85dvh] animate-fade-up overflow-y-auto rounded-t-[1.75rem] border-t border-line bg-surface p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-lift">
                    <div aria-hidden="true" class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-surface-3"></div>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :name="$user->name" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $user->name }}</p>
                                <p class="truncate text-sm text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                        <button type="button" x-on:click="close" aria-label="Fechar" class="flex size-11 items-center justify-center rounded-xl hover:bg-surface-2"><x-icon name="close" /></button>
                    </div>
                    @include('layouts.partials.context', ['tenantName' => $tenantName, 'unitLabel' => $unitLabel])
                    @if ($quick)
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach ($quick as [$route, $label, $icon])
                                <a href="{{ route($route) }}" class="flex min-h-12 items-center gap-3 rounded-2xl bg-tint px-3 text-sm font-semibold text-link"><x-icon :name="$icon" :size="18" /> {{ $label }}</a>
                            @endforeach
                        </div>
                    @endif
                    <nav aria-label="Mais opções" class="mt-4 grid grid-cols-2 gap-2">
                        @foreach ($navMore as $item)
                            <a href="{{ route($item['route']) }}" class="flex min-h-12 items-center gap-2 rounded-2xl border border-line px-3 text-sm font-medium hover:bg-surface-2"><x-icon :name="$item['icon']" :size="18" /> {{ $item['label'] }}</a>
                        @endforeach
                        <a href="{{ route('profile.edit') }}" class="flex min-h-12 items-center gap-2 rounded-2xl border border-line px-3 text-sm font-medium hover:bg-surface-2"><x-icon name="user" :size="18" /> Meu perfil</a>
                    </nav>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <x-button variant="secondary" class="w-full"><x-icon name="logout" /> Sair</x-button>
                    </form>
                </div>
            </div>

            <main id="conteudo" class="mx-auto w-full max-w-7xl px-4 pb-32 pt-6 sm:px-6 lg:px-10 lg:pb-14 lg:pt-8">
                @if (session('status'))
                    <x-alert tone="success" class="mb-6">{{ session('status') }}</x-alert>
                @endif
                @yield('content')
            </main>
        </div>

        {{-- Navegação inferior (celular): barra flutuante --}}
        <nav aria-label="Principal" class="fv-glass-strong fixed inset-x-3 bottom-[calc(0.75rem+env(safe-area-inset-bottom))] z-30 grid {{ $navCols }} gap-1 rounded-[1.4rem] border border-line/80 p-1.5 shadow-lift lg:hidden">
            @foreach ($navMobile as $item)
                @php($active = $isActive($item))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-2xl text-[0.7rem] font-semibold transition {{ $active ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'text-muted hover:text-ink' }}">
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
@endsection
