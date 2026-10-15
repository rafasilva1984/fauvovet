@extends('layouts.base')
@section('title', 'Acesso negado')
@section('body')
    <main id="conteudo" class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
        <x-logo :size="40" />
        <p class="text-sm font-semibold text-muted">403</p>
        <h1 class="text-2xl font-semibold">Acesso negado</h1>
        <p class="text-muted">Seu papel não permite acessar esta página ou ação.</p>
        <a href="{{ url('/painel') }}" class="font-semibold text-link underline underline-offset-4">Voltar ao painel</a>
    </main>
@endsection
