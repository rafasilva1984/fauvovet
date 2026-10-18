@extends('layouts.app')
@section('title', 'Parâmetros — '.$type->name)

@section('content')
    @php
        $fmt = fn ($v) => \App\Models\ExamResultValue::fmt($v);
        $speciesOptions = ['*' => 'Qualquer espécie'] + \App\Support\Species::LABELS;
    @endphp
    <x-page-header :title="'Parâmetros — '.$type->name" icon="flask" eyebrow="Catálogo de exames"
        description="Cada parâmetro tem unidade e, opcionalmente, faixa de referência por espécie. Nada vem preenchido: use as faixas do laboratório com que a clínica trabalha. No resultado, a faixa ainda pode ser ajustada à do laudo.">
        <a href="{{ route('exam-types.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Voltar ao catálogo</a>
    </x-page-header>
    @if ($errors->any())<x-alert class="mb-4">{{ $errors->first() }}</x-alert>@endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex flex-col gap-4">
            @forelse ($analytes as $a)
                <x-card>
                    <form method="POST" action="{{ route('exam-analytes.update', $a) }}" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto_auto]" novalidate>
                        @csrf
                        @method('PUT')
                        <x-field name="name" :id="'an-'.$a->id" label="Parâmetro" :value="$a->name" required />
                        <x-field name="unit" :id="'au-'.$a->id" label="Unidade" :value="$a->unit" placeholder="Ex.: mg/dL" />
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="size-4" @checked($a->active)> Ativo</label>
                        <x-button variant="secondary">Salvar<span class="sr-only"> {{ $a->name }}</span></x-button>
                    </form>
                    <div class="mt-4 border-t border-line pt-3">
                        <h3 class="mb-2 text-sm font-semibold">Faixas de referência</h3>
                        @if ($a->ranges->isEmpty())
                            <p class="text-sm text-muted">Sem faixa: o resultado aparece sem comparação até alguém informar a faixa.</p>
                        @else
                            <ul class="mb-3 flex flex-col gap-1.5">
                                @foreach ($a->ranges->sortBy(fn ($r) => $r->species === '*' ? '' : $speciesOptions[$r->species] ?? $r->species) as $r)
                                    <li class="flex flex-wrap items-center gap-2 rounded-xl bg-surface-2 px-3 py-1.5 text-sm">
                                        <span class="min-w-0 flex-1"><strong>{{ $speciesOptions[$r->species] ?? $r->species }}</strong>:
                                            {{ $r->ref_min !== null ? $fmt($r->ref_min) : '…' }} – {{ $r->ref_max !== null ? $fmt($r->ref_max) : '…' }} {{ $a->unit }}</span>
                                        <form method="POST" action="{{ route('exam-ranges.destroy', $r) }}">
                                            @csrf
                                            <button class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-link underline underline-offset-4">Remover<span class="sr-only"> faixa {{ $speciesOptions[$r->species] ?? $r->species }} de {{ $a->name }}</span></button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form method="POST" action="{{ route('exam-ranges.store', $a) }}" class="grid grid-cols-2 items-end gap-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto]" novalidate>
                            @csrf
                            <x-select name="species" :id="'rs-'.$a->id" label="Espécie" :options="$speciesOptions" value="*" class="col-span-2 sm:col-span-1" />
                            <x-field name="ref_min" :id="'rn-'.$a->id" label="Mínimo" inputmode="decimal" placeholder="Ex.: 0,5" />
                            <x-field name="ref_max" :id="'rx-'.$a->id" label="Máximo" inputmode="decimal" placeholder="Ex.: 1,5" />
                            <x-button variant="secondary" class="col-span-2 sm:col-span-1">Salvar faixa<span class="sr-only"> de {{ $a->name }}</span></x-button>
                        </form>
                    </div>
                </x-card>
            @empty
                <x-empty-state title="Nenhum parâmetro" icon="flask">Cadastre ao lado os parâmetros que este exame mede (ex.: Creatinina, mg/dL).</x-empty-state>
            @endforelse
        </div>
        <aside>
            <x-card>
                <h2 class="mb-3 font-bold">Novo parâmetro</h2>
                <form method="POST" action="{{ route('exam-analytes.store', $type) }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <x-field name="name" id="novo-param-nome" label="Nome" required placeholder="Ex.: Creatinina" />
                    <x-field name="unit" id="novo-param-unid" label="Unidade" placeholder="Ex.: mg/dL" />
                    <x-button>Cadastrar parâmetro</x-button>
                </form>
            </x-card>
        </aside>
    </div>
@endsection
