@extends('layouts.auth')
@section('title', 'Convite indisponível')
@section('heading', 'Convite indisponível')
@section('subtitle', 'Este link de convite não existe, expirou, foi cancelado ou já foi usado.')

@section('content')
    <p class="text-muted">Peça a quem convidou você para gerar um link novo na tela <strong>Equipe</strong> do FauvoVet.</p>
    <p class="mt-8 text-sm"><a href="{{ route('login') }}" class="font-semibold text-link underline underline-offset-4">Ir para o login</a></p>
@endsection
