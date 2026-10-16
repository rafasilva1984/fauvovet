@extends('layouts.base')
@section('title', 'Em manutenção')
@section('body')
    <main id="conteudo" class="fv-mesh flex min-h-dvh items-center justify-center px-6"><div class="flex w-full max-w-md animate-fade-up flex-col items-center gap-3 rounded-[1.75rem] border border-line bg-surface p-8 text-center shadow-lift">
        <x-logo :size="52" />
        <p class="fv-gradient-text text-5xl font-extrabold tracking-tight">503</p>
        <h1 class="text-2xl font-bold tracking-tight">Em manutenção</h1>
        <p class="text-muted">O sistema está passando por manutenção. Volte em alguns minutos.</p>
        <a href="{{ url('/painel') }}" class="font-semibold text-link underline underline-offset-4">Voltar ao painel</a>
    </div></main>
@endsection
