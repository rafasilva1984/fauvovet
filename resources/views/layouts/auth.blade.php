@extends('layouts.base')

@section('body')
    <div class="fv-mesh grid grid-cols-1 min-h-dvh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        {{-- Painel de marca (desktop): degradê da clínica, brilhos animados e prévia ilustrativa do produto --}}
        <aside class="relative hidden overflow-hidden bg-brand fv-gradient text-on-brand lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div aria-hidden="true" class="fv-orb fv-orb-a absolute -right-24 -top-24 size-[26rem] opacity-40"></div>
            <div aria-hidden="true" class="fv-orb fv-orb-b absolute -bottom-32 -left-20 size-[24rem] opacity-30"></div>
            <div aria-hidden="true" class="fv-dots absolute inset-0 opacity-25"></div>

            <div class="relative flex items-center gap-3">
                <x-logo :size="44" />
                <span class="text-xl font-bold tracking-tight">FauvoVet</span>
            </div>

            <div class="relative max-w-lg">
                <p class="text-xs font-bold uppercase tracking-[0.2em] opacity-85">Plataforma veterinária</p>
                <p class="mt-3 text-[2.6rem] font-bold leading-[1.1] tracking-tight">O cuidado de cada paciente, organizado do agendamento ao prontuário.</p>
                <p class="mt-4 max-w-md text-lg opacity-90">Para veterinários autônomos, clínicas e hospitais — cada clínica com seu ambiente isolado e a sua identidade.</p>

                {{-- Prévia ilustrativa (decorativa; dados fictícios) --}}
                <div aria-hidden="true" class="relative mt-10 h-48">
                    <div class="animate-float absolute left-0 top-0 w-72 rounded-2xl bg-white/95 p-4 text-[#0d1b21] shadow-2xl">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-[#4c5f66]">Hoje · 09:00</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="fv-tone fv-tone-0 flex size-10 items-center justify-center rounded-xl"><x-icon name="paw" :size="20" /></span>
                            <div><p class="font-bold">Thor</p><p class="text-xs text-[#4c5f66]">Consulta · Dra. Ana</p></div>
                            <span class="ml-auto rounded-full bg-[#e3f4ea] px-2 py-0.5 text-[0.65rem] font-bold text-[#136b42]">Confirmado</span>
                        </div>
                    </div>
                    <div class="animate-float absolute right-2 top-20 w-64 rounded-2xl bg-white/95 p-4 text-[#0d1b21] shadow-2xl [animation-delay:-3.5s]">
                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-[#4c5f66]">Fila de espera</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="fv-tone fv-tone-1 flex size-10 items-center justify-center rounded-xl"><x-icon name="paw" :size="20" /></span>
                            <div><p class="font-bold">Kiwi</p><p class="text-xs text-[#4c5f66]">Calopsita · aguardando 6 min</p></div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="relative flex items-center gap-2 text-sm opacity-90"><x-icon name="shield" /> Dados isolados por clínica · sessão protegida · LGPD by design</p>
        </aside>

        <main id="conteudo" class="relative flex items-start justify-center px-4 py-10 sm:items-center sm:px-8">
            <div class="w-full animate-fade-up @yield('width', 'max-w-md')">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-logo :size="40" />
                    <span class="text-xl font-bold tracking-tight">FauvoVet</span>
                </div>
                <div class="rounded-[1.75rem] border border-line bg-surface p-6 shadow-lift sm:p-9">
                    <h1 class="text-[1.75rem] font-bold tracking-tight sm:text-[2rem]">@yield('heading')</h1>
                    @hasSection('subtitle')
                        <p class="mt-2 text-muted">@yield('subtitle')</p>
                    @endif
                    <div class="mt-7">@yield('content')</div>
                </div>
                <p class="mt-6 text-center text-xs text-muted">FauvoVet · software livre (MIT) · seus dados ficam no ambiente da sua clínica</p>
            </div>
        </main>
    </div>
@endsection
