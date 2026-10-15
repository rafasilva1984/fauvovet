@extends('layouts.app')
@section('title', 'Aparência')

@section('content')
    <x-page-header title="Aparência" description="Cores e modo de exibição da sua clínica. Valem para todos os usuários do ambiente. Logo e timbre de documentos chegam em breve." />
    <form method="POST" action="{{ route('settings.appearance.update') }}" class="grid gap-6 lg:grid-cols-2" novalidate
        x-data="themeEditor" data-primary="{{ old('primary', $theme['primary']) }}" data-secondary="{{ old('secondary', $theme['secondary']) }}"
        data-default-primary="{{ $default['primary'] }}" data-default-secondary="{{ $default['secondary'] }}">
        @csrf
        @method('PUT')
        <x-card class="flex flex-col gap-5">
            @foreach (['primary' => 'Primária', 'secondary' => 'Secundária'] as $key => $label)
                <div class="flex flex-col gap-1.5">
                    <label for="cor-{{ $key }}" class="text-sm font-medium">{{ $label }}</label>
                    <div class="flex items-center gap-3">
                        <input type="color" aria-label="{{ $label }} — seletor" x-bind:value="{{ $key }}" x-on:input="{{ $key === 'primary' ? 'pickPrimary' : 'pickSecondary' }}"
                            class="size-11 cursor-pointer rounded-xl border border-line bg-surface p-1">
                        <input id="cor-{{ $key }}" name="{{ $key }}" x-model="{{ $key }}" maxlength="7" spellcheck="false" aria-describedby="cor-{{ $key }}-ratio"
                            value="{{ old($key, $theme[$key]) }}"
                            class="min-h-11 w-32 rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase">
                    </div>
                    <p id="cor-{{ $key }}-ratio" class="text-xs" x-bind:class="{{ $key }}Class" x-text="{{ $key }}Text" aria-live="polite"></p>
                    @error($key)<p class="text-xs font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Modo</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['system' => 'Automático', 'light' => 'Claro', 'dark' => 'Escuro'] as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-line text-sm font-medium has-[:checked]:border-brand has-[:checked]:bg-surface-2 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                            <input type="radio" name="mode" value="{{ $value }}" class="sr-only" @checked(old('mode', $theme['mode']) === $value)> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex flex-wrap gap-2">
                <x-button x-bind:disabled="invalid">Salvar aparência</x-button>
                <x-button type="button" variant="secondary" x-on:click="restore">Restaurar padrão FauvoVet</x-button>
            </div>
            <p class="text-sm text-danger" x-show="invalid" x-cloak>Ajuste as cores para atender ao contraste mínimo antes de salvar.</p>
        </x-card>

        <section aria-label="Pré-visualização">
            <x-card class="flex flex-col gap-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Pré-visualização</p>
                <div class="flex items-center gap-3"><x-logo :size="40" /><span class="text-lg font-semibold">{{ $fvTenant?->trade_name }}</span></div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex min-h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">Botão primário</span>
                    <span class="inline-flex min-h-10 items-center rounded-xl bg-accent px-4 text-sm font-semibold text-on-accent">Destaque</span>
                </div>
                <p class="text-sm">Texto com <span class="font-semibold text-link underline underline-offset-4">link de exemplo</span> sobre a superfície.</p>
            </x-card>
        </section>
    </form>
@endsection
