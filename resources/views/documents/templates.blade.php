@extends('layouts.app')
@section('title', 'Modelos de documento')

@section('content')
    <x-page-header title="Modelos de documento" icon="list" eyebrow="Configurações" description="Textos-base de receitas, atestados, orientações e termos. Ao editar, os documentos já emitidos não mudam.">
        <a href="{{ route('document-templates.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo modelo</a>
    </x-page-header>
    <x-card class="p-1.5">
        <ul class="divide-y divide-line">
            @foreach ($templates as $t)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl px-3.5 py-3">
                    <span class="min-w-0">
                        <span class="block font-semibold {{ $t->active ? 'text-ink' : 'text-muted' }}">{{ $t->name }}</span>
                        <span class="block text-sm text-muted">{{ \App\Models\DocumentTemplate::CATEGORIES[$t->category] }} · versão {{ $t->version }}{{ $t->active ? '' : ' · desativado' }}</span>
                    </span>
                    <a href="{{ route('document-templates.edit', $t) }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-link underline underline-offset-4">Editar<span class="sr-only"> {{ $t->name }}</span></a>
                </li>
            @endforeach
        </ul>
    </x-card>
@endsection
