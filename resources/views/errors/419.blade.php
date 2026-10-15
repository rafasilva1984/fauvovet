@extends('layouts.base')
@section('title', 'Sessão expirada')
@section('body')
    <main id="conteudo" class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
        <x-logo :size="40" />
        <p class="text-sm font-semibold text-muted">419</p>
        <h1 class="text-2xl font-semibold">Sessão expirada</h1>
        <p class="text-muted">Por segurança, a página expirou. Volte e tente novamente.</p>
        <a href="{{ url('/painel') }}" class="font-semibold text-link underline underline-offset-4">Voltar ao painel</a>
    </main>
@endsection
