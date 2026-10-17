@extends('layouts.app')
@section('title', 'Busca clínica')

@section('content')
    @php
        $dxTone = ['confirmed' => 'danger', 'suspected' => 'warning', 'ruled_out' => 'neutral'];
        $sources = \App\Services\ClinicalSearchService::SOURCES;
        $examples = ['dermatite', '"recusa alimentar" ou "não se alimenta"', 'penicilina antibiótico', 'renal NÃO felino', 'tonometria'];
    @endphp
    <x-page-header title="Busca clínica" icon="search" eyebrow="Prontuário"
        description="Procure palavras e frases em todo o prontuário da clínica: atendimentos finalizados, diagnósticos, adendos, alertas, documentos emitidos, vacinas e o texto de anexos PDF/DOCX." />

    @if ($demo !== null)
        <x-alert tone="warning" class="mb-4">
            Os dados de demonstração desta clínica são de uma versão anterior e não têm os exemplos novos.
            @if ($fvCan('tenant:update'))<a href="{{ route('settings.demo-data') }}" class="font-semibold underline underline-offset-4">Atualizar demonstração</a>.@else Peça ao titular para atualizar em Configurações → Dados de demonstração.@endif
        </x-alert>
    @endif
    @if ($pendingAttachments > 0)
        <x-alert tone="info" class="mb-4">
            {{ $pendingAttachments }} {{ $pendingAttachments === 1 ? 'anexo enviado antes desta versão ainda não teve o texto lido' : 'anexos enviados antes desta versão ainda não tiveram o texto lido' }}.
            @if ($fvCan('clinical:write'))
                <form method="POST" action="{{ route('search.attachments') }}" class="mt-2">@csrf<x-button variant="secondary">Ler anexos pendentes</x-button></form>
            @endif
        </x-alert>
    @endif
    @if ($built)
        <x-alert tone="info" class="mb-4">O índice da busca desta clínica acabou de ser construído ({{ $status['documents'] ?? 0 }} trechos).</x-alert>
    @endif

    <form method="GET" action="{{ route('search.index') }}" class="mb-6 rounded-[1.25rem] border border-line bg-surface p-4 shadow-card" role="search" aria-label="Busca no prontuário">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <x-field name="q" label="O que procurar no prontuário" type="search" :value="$f['q']" autocomplete="off"
                hint="Sem acento e no singular também funciona. Frase exata entre aspas; ou/OU para alternativas; NÃO (ou -palavra) para excluir." />
            <x-button><x-icon name="search" /> Buscar</x-button>
        </div>
        @php
            $active = count(array_filter([$f['especie'], $f['periodo'], $f['origem'], $f['situacao'], $f['ordem'] !== 'relevancia' ? 'x' : '']));
        @endphp
        <details class="group mt-3 border-t border-line pt-3" @if ($active > 0 && ($result['total'] ?? 0) === 0) open @endif>
            <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 font-semibold text-link">
                <x-icon name="chevron-right" :size="16" class="transition group-open:rotate-90" /> Filtros e ordem
                @if ($active > 0)<x-badge tone="tint">{{ $active }}</x-badge>@endif
            </summary>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-select name="especie" label="Espécie" :options="\App\Support\Species::LABELS" :value="$f['especie']" placeholder="Todas" />
            <x-select name="periodo" label="Período" :options="\App\Services\ClinicalSearchService::PERIODS" :value="$f['periodo']" placeholder="Qualquer data" />
            <x-select name="origem" label="Onde procurar" :options="$sources" :value="$f['origem']" placeholder="Todo o prontuário" />
            <x-select name="situacao" label="Situação do diagnóstico" :options="\App\Models\EncounterDiagnosis::STATUS" :value="$f['situacao']" placeholder="Qualquer" />
            <x-select name="ordem" label="Ordenar por" :options="\App\Services\ClinicalSearchService::SORTS" :value="$f['ordem']" />
        </div>
        <div class="mt-3"><x-button variant="secondary">Aplicar filtros</x-button></div>
        </details>
    </form>

    @if ($result === null)
        <x-card class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Exemplos</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach ($examples as $ex)
                    <li><a href="{{ route('search.index', ['q' => $ex]) }}" class="inline-flex min-h-11 items-center rounded-xl border border-line bg-surface-2 px-3 font-mono text-sm hover:bg-tint">{{ $ex }}</a></li>
                @endforeach
            </ul>
            <p class="text-sm text-muted">Vários termos sem OU precisam aparecer todos no prontuário do mesmo paciente, mesmo em registros diferentes (ex.: alergia num alerta e antibiótico na conduta).</p>
        </x-card>
    @elseif ($result['total'] === 0)
        <x-empty-state title="Nada encontrado no prontuário" icon="search">
            @if ($result['parsed']['groups'] === []) Digite ao menos uma palavra com 3 letras ou mais. @else Tente menos palavras, outra grafia ou remova filtros. Rascunhos não entram na busca. @endif
        </x-empty-state>
    @else
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-muted" aria-live="polite">{{ $result['total'] }} {{ $result['total'] === 1 ? 'paciente' : 'pacientes' }} · {{ $result['hits'] }} {{ $result['hits'] === 1 ? 'trecho' : 'trechos' }}</p>
            @foreach ($result['parsed']['exclude'] as $x)<x-badge tone="neutral">sem “{{ $x }}”</x-badge>@endforeach
            @if ($result['parsed']['ignored'] !== [])<span class="text-xs text-muted">Ignorado: {{ implode(', ', $result['parsed']['ignored']) }}</span>@endif
        </div>
        @if ($result['truncated'])
            <x-alert tone="warning" class="mb-3">Muitos registros com estes termos: a lista pode estar incompleta. Refine com mais palavras ou filtros.</x-alert>
        @endif
        <ul class="flex flex-col gap-4">
            @foreach ($result['results'] as $r)
                <li>
                    @php
                        $pt = $r['patient'];
                    @endphp
                    <x-card class="flex flex-col gap-3">
                        <a href="{{ route('patients.show', $pt->id) }}" class="group flex items-center gap-3">
                            <x-avatar :name="$pt->name" icon="paw" :tone="\App\Support\Species::tone($pt->species)" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold group-hover:text-link group-hover:underline">{{ $pt->name }}</span>
                                <span class="block truncate text-sm text-muted">{{ \App\Support\Species::label($pt->species) }}{{ $pt->species_detail ? ' · '.$pt->species_detail : '' }}{{ $pt->breed ? ' · '.$pt->breed : '' }}{{ $pt->status !== 'active' ? ' · '.(\App\Models\Patient::STATUS[$pt->status] ?? $pt->status) : '' }}</span>
                            </span>
                            <x-icon name="chevron-right" class="text-muted" />
                        </a>
                        <ul class="flex flex-col gap-2" aria-label="Trechos encontrados para {{ $pt->name }}">
                            @foreach ($r['evidence'] as $ev)
                                @php
                                    $pt = $r['patient'];
                                    $href = match (true) {
                                        $ev['source'] === 'document' => route('documents.show', $ev['source_id']),
                                        $ev['encounter_id'] !== null => route('encounters.show', $ev['encounter_id']),
                                        default => route('patients.show', $pt->id),
                                    };
                                @endphp
                                <li class="rounded-xl border border-line bg-surface-2/50 p-3">
                                    <a href="{{ $href }}" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold hover:text-link hover:underline">
                                        @if ($ev['dx_status'])<x-badge :tone="$dxTone[$ev['dx_status']]" :dot="true">{{ \App\Models\EncounterDiagnosis::STATUS[$ev['dx_status']] }}</x-badge>@endif
                                        <span>{{ $ev['label'] }}</span>
                                        <span class="font-normal text-muted">· {{ $ev['when']->format('d/m/Y') }}{{ $ev['author'] ? ' · '.$ev['author'] : '' }}</span>
                                    </a>
                                    <p class="fv-snippet mt-1 text-sm leading-relaxed">{!! $ev['snippet'] !!}</p>
                                </li>
                            @endforeach
                        </ul>
                        @if ($r['more'] > 0)
                            <p class="text-sm text-muted">+ {{ $r['more'] }} {{ $r['more'] === 1 ? 'outro trecho' : 'outros trechos' }} — <a href="{{ route('patients.show', $pt->id) }}" class="font-semibold text-link underline underline-offset-4">abrir o histórico</a></p>
                        @endif
                    </x-card>
                </li>
            @endforeach
        </ul>
        @if ($result['pages'] > 1)
            <nav aria-label="Páginas" class="mt-4 flex flex-wrap items-center gap-2">
                @if ($f['pagina'] > 1)<a class="inline-flex min-h-11 items-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold hover:bg-tint" href="{{ route('search.index', [...request()->query(), 'pagina' => $f['pagina'] - 1]) }}">Anterior</a>@endif
                <span class="text-sm text-muted">Página {{ $f['pagina'] }} de {{ $result['pages'] }}</span>
                @if ($f['pagina'] < $result['pages'])<a class="inline-flex min-h-11 items-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold hover:bg-tint" href="{{ route('search.index', [...request()->query(), 'pagina' => $f['pagina'] + 1]) }}">Próxima</a>@endif
            </nav>
        @endif
    @endif

    <footer class="mt-8 flex flex-wrap items-center gap-3 text-xs text-muted">
        <span>Índice: {{ $status['documents'] ?? 0 }} trechos · atualizado em {{ isset($status['indexed_at']) ? $status['indexed_at']->timezone('America/Sao_Paulo')->format('d/m/Y H:i') : '—' }}. Atendimentos entram na busca ao serem finalizados.</span>
        @if ($fvCan('tenant:update'))
            <form method="POST" action="{{ route('search.rebuild') }}">@csrf<button class="min-h-11 font-semibold text-link underline underline-offset-4">Reconstruir índice</button></form>
        @endif
    </footer>
@endsection
