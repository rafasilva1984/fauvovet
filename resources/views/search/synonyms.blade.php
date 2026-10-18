@extends('layouts.app')
@section('title', 'Sinônimos da busca')

@section('content')
    <x-page-header title="Sinônimos da busca" icon="list" eyebrow="Configurações"
        description="Expressões que a sua clínica considera equivalentes na Busca clínica. Procurar uma encontra todas. O sistema não traz sinônimos prontos: cada grupo é decisão da equipe." />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="grupos" class="flex flex-col gap-3">
            <h2 id="grupos" class="sr-only">Grupos cadastrados</h2>
            @forelse ($groups as $g)
                <x-card>
                    <form method="POST" action="{{ route('search.synonyms.update', $g) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        @method('PUT')
                        <x-textarea :name="'terms_'.$g->id" :id="'terms-'.$g->id" :label="'Grupo: '.implode(' = ', $g->lines())" :value="$g->terms" rows="3" />
                        <div class="flex flex-wrap gap-2">
                            <x-button variant="secondary">Salvar grupo</x-button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('search.synonyms.destroy', $g) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 text-sm font-semibold text-danger underline underline-offset-4">Excluir grupo<span class="sr-only"> {{ implode(', ', $g->lines()) }}</span></button>
                    </form>
                </x-card>
            @empty
                <x-empty-state title="Nenhum sinônimo cadastrado" icon="list">Crie o primeiro grupo ao lado.</x-empty-state>
            @endforelse
        </section>
        <aside>
            <x-card>
                <h2 class="mb-3 font-bold">Novo grupo</h2>
                <form method="POST" action="{{ route('search.synonyms.store') }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <x-textarea name="terms" label="Expressões equivalentes" rows="5" required hint="Uma por linha. Ex.: recusa alimentar / não se alimenta / anorexia." />
                    <x-button>Criar grupo</x-button>
                </form>
            </x-card>
        </aside>
    </div>
@endsection
