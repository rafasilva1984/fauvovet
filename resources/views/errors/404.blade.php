@extends('layouts.base')
@section('title', 'Página não encontrada')
@section('body')
    <main id="conteudo" class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 px-6 text-center">
        <x-logo :size="40" />
        <p class="text-sm font-semibold text-muted">404</p>
        <h1 class="text-2xl font-semibold">Página não encontrada</h1>
        <p class="text-muted">O endereço pode ter mudado ou você não tem acesso a ele.</p>
        <a href="{{ url('/painel') }}" class="font-semibold text-link underline underline-offset-4">Voltar ao painel</a>
    </main>
@endsection
