@extends('layouts.app')
@section('title', 'Catálogo de exames')

@section('content')
    <x-page-header title="Catálogo de exames" icon="list" eyebrow="Configurações" description="Exames que a clínica pede. A lista inicial é genérica: ajuste nomes, materiais, parâmetros e faixas de referência ao laboratório que vocês usam." />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card class="p-1.5">
            <ul class="divide-y divide-line">
                @foreach ($types as $t)
                    <li class="p-3">
                        <details>
                            <summary class="flex min-h-11 cursor-pointer flex-wrap items-center gap-2">
                                <span class="font-semibold {{ $t->active ? '' : 'text-muted' }}">{{ $t->name }}</span>
                                <span class="text-sm text-muted">{{ \App\Models\ExamType::CATEGORIES[$t->category] }}{{ $t->sample ? ' · '.$t->sample : '' }}{{ $t->analytes_count ? ' · '.$t->analytes_count.' '.($t->analytes_count === 1 ? 'parâmetro' : 'parâmetros') : '' }}{{ $t->active ? '' : ' · desativado' }}</span>
                            </summary>
                            <form method="POST" action="{{ route('exam-types.update', $t) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3" novalidate>
                                @csrf
                                @method('PUT')
                                <x-field name="name" :id="'ex-n-'.$t->id" label="Nome" :value="$t->name" required />
                                <x-select name="category" :id="'ex-c-'.$t->id" label="Categoria" :options="\App\Models\ExamType::CATEGORIES" :value="$t->category" />
                                <x-field name="sample" :id="'ex-s-'.$t->id" label="Material" :value="$t->sample" />
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="size-4" @checked($t->active)> Ativo</label>
                                <div class="sm:col-span-2"><x-button variant="secondary">Salvar {{ $t->name }}</x-button></div>
                                <a href="{{ route('exam-types.show', $t) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-link underline underline-offset-4">Parâmetros e faixas<span class="sr-only"> de {{ $t->name }}</span></a>
                            </form>
                        </details>
                    </li>
                @endforeach
            </ul>
        </x-card>
        <aside>
            <x-card>
                <h2 class="mb-3 font-bold">Novo exame</h2>
                <form method="POST" action="{{ route('exam-types.store') }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <x-field name="name" id="novo-exame-nome" label="Nome" required />
                    <x-select name="category" id="novo-exame-cat" label="Categoria" :options="\App\Models\ExamType::CATEGORIES" value="lab" />
                    <x-field name="sample" id="novo-exame-mat" label="Material" placeholder="Ex.: soro, urina" />
                    <x-button>Cadastrar exame</x-button>
                </form>
            </x-card>
        </aside>
    </div>
@endsection
