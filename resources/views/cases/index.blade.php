@extends('layouts.app')
@section('title', 'Casos científicos')

@section('content')
    <x-page-header title="Casos científicos" icon="flask" eyebrow="Clínica" description="Séries e relatos montados com os casos já tratados pela equipe, prontos para estudo e publicação." />

    @if ($cases->isEmpty())
        <x-empty-state title="Nenhum caso científico ainda" icon="flask">
            Para uma <strong>série de casos</strong>, use os filtros avançados em <a href="{{ route('patients.index') }}" class="font-semibold text-link underline">Pacientes</a> (ex.: diagnóstico confirmado + espécie) e toque em <em>Criar série de casos</em>.
            Para um <strong>relato de caso</strong>, abra um atendimento finalizado e toque em <em>Criar relato de caso</em>.
        </x-empty-state>
    @else
        <ul class="grid grid-cols-1 gap-3 xl:grid-cols-2">
            @foreach ($cases as $c)
                <li>
                    <a href="{{ route('cases.show', $c) }}" class="group block">
                        <x-card :hover="true" class="flex items-center gap-4 p-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-tint text-link"><x-icon name="flask" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-ink">{{ $c->title }}</span>
                                    <x-badge :tone="$c->kind === 'series' ? 'tint' : 'neutral'">{{ \App\Models\ClinicalCase::KINDS[$c->kind] }}</x-badge>
                                    @if (! $c->isOpen())<x-badge tone="success">Encerrado</x-badge>@endif
                                </span>
                                <span class="block truncate text-sm text-muted">{{ $c->entries_count }} {{ $c->entries_count === 1 ? 'caso' : 'casos' }} · {{ $c->author?->name }} · atualizado em {{ $c->updated_at?->timezone('America/Sao_Paulo')->format('d/m/Y') }}</span>
                            </span>
                            <x-icon name="chevron-right" :size="18" class="shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-link" />
                        </x-card>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $cases->links('partials.pagination') }}</div>
    @endif
@endsection
