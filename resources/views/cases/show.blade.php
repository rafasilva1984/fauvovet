@extends('layouts.app')
@section('title', $case->title)

@section('content')
    @php
        $open = $case->isOpen();
        $years = fn (float $y) => number_format($y, 1, ',', '').' anos';
        $consentTone = ['pending' => 'warning', 'granted' => 'success', 'refused' => 'neutral'];
    @endphp
    <x-page-header icon="flask" :eyebrow="\App\Models\ClinicalCase::KINDS[$case->kind]" :title="$case->title"
        :description="'Criado por '.($case->author?->name ?? '—').' · '.\App\Models\ClinicalCase::STATUS[$case->status]">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('cases.export', [$case, 'csv']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line bg-surface px-3.5 text-sm font-semibold shadow-card hover:bg-tint"><x-icon name="list" :size="18" /> Planilha (CSV)</a>
            <a href="{{ route('cases.export', [$case, 'pdf']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand fv-gradient px-3.5 text-sm font-semibold text-on-brand shadow-glow hover:brightness-110"><x-icon name="copy" :size="18" /> PDF anonimizado</a>
        </div>
    </x-page-header>

    <section aria-labelledby="h-stats" class="mb-6">
        <h2 id="h-stats" class="sr-only">Estatísticas da série</h2>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-card class="p-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted">Casos</p><p class="mt-1 text-3xl font-extrabold">{{ $stats['total'] }}</p></x-card>
            <x-card class="p-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted">Consentimento autorizado</p><p class="mt-1 text-3xl font-extrabold">{{ $stats['consent']['Autorizado'] ?? 0 }}<span class="text-base font-semibold text-muted"> / {{ $stats['total'] }}</span></p></x-card>
            <x-card class="p-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted">Idade (mediana)</p><p class="mt-1 text-2xl font-extrabold">{{ $stats['age'] ? $years($stats['age']['median']) : '—' }}</p>
                @if ($stats['age'])<p class="text-xs text-muted">{{ $years($stats['age']['min']) }} a {{ $years($stats['age']['max']) }} · n={{ $stats['age']['n'] }}</p>@endif</x-card>
            <x-card class="p-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted">Desfechos avaliados</p><p class="mt-1 text-3xl font-extrabold">{{ $stats['total'] - ($stats['outcome']['Não avaliado'] ?? 0) }}<span class="text-base font-semibold text-muted"> / {{ $stats['total'] }}</span></p></x-card>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach (['species' => 'Espécie', 'sex' => 'Sexo', 'outcome' => 'Desfecho'] as $key => $label)
                <x-card class="p-4">
                    <h3 class="text-sm font-bold">{{ $label }}</h3>
                    <dl class="mt-2 flex flex-col gap-1.5 text-sm">
                        @foreach ($stats[$key] as $name => $n)
                            <div class="flex items-center gap-2">
                                <dt class="min-w-0 flex-1 truncate">{{ $name }}</dt>
                                <dd class="font-semibold tabular-nums">{{ $n }} <span class="font-normal text-muted">({{ round($n * 100 / max(1, $stats['total'])) }}%)</span></dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section aria-labelledby="h-cases" class="order-2 xl:order-1">
            <h2 id="h-cases" class="mb-3 text-lg font-bold">Casos</h2>
            <x-alert tone="info" class="mb-3">Os nomes aparecem só aqui, para a equipe. Na exportação, cada paciente vira o seu “Caso”, e só os casos com consentimento <strong>Autorizado</strong> entram.</x-alert>
            <ul class="flex flex-col gap-3">
                @foreach ($case->entries as $e)
                    <li id="{{ $e->id }}">
                        <x-card class="p-4">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="rounded-lg bg-tint px-2 py-0.5 text-sm font-extrabold text-link">{{ $e->label }}</span>
                                <a href="{{ route('patients.show', $e->patient_id) }}" class="font-bold hover:text-link hover:underline">{{ $e->patient->name }}</a>
                                <span class="text-sm text-muted">{{ collect([\App\Support\Species::label($e->patient->species), $e->patient->breed, \App\Services\ClinicalCaseService::ageAt($e->patient, $e->encounter?->started_at)])->filter()->join(' · ') }}</span>
                                <x-badge :tone="$consentTone[$e->consent]" class="ml-auto">Consentimento: {{ \App\Models\ClinicalCasePatient::CONSENT[$e->consent] }}</x-badge>
                            </div>
                            @if ($e->encounter)
                                <a href="{{ route('encounters.show', $e->encounter_id) }}" class="mt-1 inline-flex min-h-8 items-center text-sm text-link hover:underline">Atendimento de referência: {{ $e->encounter->started_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</a>
                            @else
                                <p class="mt-1 text-sm text-muted">Sem atendimento finalizado de referência.</p>
                            @endif
                            @if ($open)
                                <form method="POST" action="{{ route('cases.entries.update', [$case, $e]) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3" novalidate>
                                    @csrf @method('PUT')
                                    <x-select name="outcome" :id="'o-'.$e->id" label="Desfecho" :options="\App\Models\ClinicalCasePatient::OUTCOMES" :value="$e->outcome" />
                                    <x-select name="consent" :id="'c-'.$e->id" label="Consentimento do tutor" :options="\App\Models\ClinicalCasePatient::CONSENT" :value="$e->consent" />
                                    <x-field name="consent_on" :id="'d-'.$e->id" type="date" label="Data do consentimento" :value="$e->consent_on?->format('Y-m-d')" />
                                    <x-textarea name="notes" :id="'n-'.$e->id" label="Observações do caso" :value="$e->notes" rows="2" class="sm:col-span-3" hint="Vão para a exportação. Não escreva nomes, telefones ou documentos." />
                                    <div class="flex flex-wrap items-center gap-3 sm:col-span-3">
                                        <x-button variant="secondary">Salvar {{ $e->label }}</x-button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('cases.entries.remove', [$case, $e]) }}" class="mt-2">
                                    @csrf
                                    <button class="inline-flex min-h-8 items-center text-xs font-semibold text-muted hover:text-danger hover:underline">Remover {{ $e->label }} do caso</button>
                                </form>
                            @else
                                <p class="mt-2 text-sm"><strong>Desfecho:</strong> {{ \App\Models\ClinicalCasePatient::OUTCOMES[$e->outcome] }}@if ($e->notes) · {{ $e->notes }}@endif</p>
                            @endif
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </section>

        <aside class="order-1 xl:order-2" aria-labelledby="h-study">
            <x-card class="xl:sticky xl:top-24">
                <h2 id="h-study" class="text-lg font-bold">Estudo</h2>
                @if ($case->criteria)
                    <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Critérios de seleção">
                        @foreach (\App\Services\PatientSearch::describe($case->criteria) as $chip)<x-badge tone="tint">{{ $chip }}</x-badge>@endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('cases.update', $case) }}" class="mt-4 flex flex-col gap-4" novalidate>
                    @csrf @method('PUT')
                    <x-field name="title" label="Título" :value="$case->title" required maxlength="160" />
                    <x-textarea name="question" label="Pergunta clínica ou objetivo" :value="$case->question" required rows="3" />
                    <x-textarea name="summary" label="Resumo e discussão" :value="$case->summary" rows="6" hint="Escrito pela equipe. Vai para o PDF; nomes conhecidos são omitidos automaticamente." />
                    <x-field name="specialty" label="Área ou especialidade" :value="$case->specialty" maxlength="80" />
                    <x-select name="status" label="Situação" :options="\App\Models\ClinicalCase::STATUS" :value="$case->status" hint="Encerrado: os casos ficam somente leitura." />
                    <x-button>Salvar estudo</x-button>
                </form>
            </x-card>
        </aside>
    </div>
@endsection
