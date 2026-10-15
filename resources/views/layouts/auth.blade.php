@extends('layouts.base')

@section('body')
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <aside class="relative hidden overflow-hidden bg-brand text-on-brand lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="flex items-center gap-3">
                <x-logo :size="40" />
                <span class="text-xl font-semibold tracking-tight">FauvoVet</span>
            </div>
            <div class="relative z-10 max-w-md">
                <p class="text-3xl font-semibold leading-tight tracking-tight">O histórico clínico de cada paciente, organizado e protegido.</p>
                <p class="mt-4 opacity-85">Plataforma para veterinários autônomos, clínicas e hospitais — cada clínica com seu ambiente isolado.</p>
            </div>
            <p class="relative z-10 flex items-center gap-2 text-sm opacity-85"><x-icon name="shield" /> Dados isolados por clínica · sessão protegida</p>
            <div aria-hidden="true" class="fv-glow absolute -bottom-24 -right-24 size-[28rem] rounded-full opacity-20"></div>
        </aside>
        <main id="conteudo" class="flex items-start justify-center px-4 py-10 sm:items-center sm:px-8">
            <div class="w-full @yield('width', 'max-w-md')">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-logo :size="36" />
                    <span class="text-lg font-semibold tracking-tight">FauvoVet</span>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">@yield('heading')</h1>
                @hasSection('subtitle')
                    <p class="mt-2 text-muted">@yield('subtitle')</p>
                @endif
                <div class="mt-8">@yield('content')</div>
            </div>
        </main>
    </div>
@endsection
