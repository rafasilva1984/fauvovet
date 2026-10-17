@extends('layouts.app')
@section('title', $kind === 'series' ? 'Nova série de casos' : 'Novo relato de caso')

@section('content')
    <x-page-header icon="flask" eyebrow="Casos científicos" :title="$kind === 'series' ? 'Nova série de casos' : 'Novo relato de caso'"
        :description="$kind === 'series' ? 'Os pacientes que atendem aos filtros da busca entram como casos numerados.' : 'O paciente deste atendimento entra como Caso 01.'" />

    <div class="grid max-w-5xl grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card class="order-2 lg:order-1">
            @if ($kind === 'series' && $total === 0)
                <x-empty-state title="Nenhum paciente atende a estes filtros" icon="paw">
                    <a href="{{ route('patients.index', request()->query()) }}" class="font-semibold text-link underline">Voltar à busca</a> e ajustar os filtros.
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('cases.store', request()->query()) }}" class="flex flex-col gap-5" novalidate>
                    @csrf
                    <x-field name="title" label="Título" required maxlength="160" :hint="$kind === 'series' ? 'Ex.: Dermatite atópica em cães — resposta ao tratamento' : 'Ex.: Uveíte em calopsita — relato de caso'" />
                    <x-textarea name="question" label="Pergunta clínica ou objetivo" required rows="3" hint="O que o estudo quer responder. Ex.: qual a evolução após 30 dias de tratamento?" />
                    <x-field name="specialty" label="Área ou especialidade" maxlength="80" hint="Ex.: dermatologia, oftalmologia, animais exóticos" />
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <x-button>{{ $kind === 'series' ? 'Criar série com '.min($total, \App\Models\ClinicalCase::MAX_PATIENTS).' '.($total === 1 ? 'caso' : 'casos') : 'Criar relato de caso' }}</x-button>
                        <a href="{{ $kind === 'series' ? route('patients.index', request()->query()) : route('encounters.show', $encounter) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Voltar</a>
                    </div>
                </form>
            @endif
        </x-card>

        <aside class="order-1 flex flex-col gap-3 lg:order-2" aria-label="Origem dos casos">
            <x-card>
                @if ($kind === 'series')
                    <h2 class="text-sm font-bold">Critérios da série</h2>
                    <p class="mt-1 text-2xl font-extrabold">{{ $total }} <span class="text-sm font-semibold text-muted">{{ $total === 1 ? 'paciente' : 'pacientes' }}</span></p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (\App\Services\PatientSearch::describe($f) as $chip)<x-badge tone="tint">{{ $chip }}</x-badge>@endforeach
                    </div>
                    @if ($total > \App\Models\ClinicalCase::MAX_PATIENTS)
                        <p class="mt-2 text-xs font-semibold text-warning">Entram os primeiros {{ \App\Models\ClinicalCase::MAX_PATIENTS }}; refine os filtros para uma série menor.</p>
                    @endif
                    <p class="mt-3 text-xs text-muted">Os critérios ficam gravados no caso, para quem quiser reproduzir a seleção.</p>
                @else
                    <h2 class="text-sm font-bold">Atendimento de origem</h2>
                    <p class="mt-1 font-semibold">{{ $encounter->patient->name }} · {{ $encounter->started_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</p>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($encounter->diagnoses as $d)
                            <li><x-badge :tone="['confirmed' => 'danger', 'suspected' => 'warning', 'ruled_out' => 'neutral'][$d->status]">{{ \App\Models\EncounterDiagnosis::STATUS[$d->status] }}: {{ $d->description }}</x-badge></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-alert tone="info">Na exportação, cada paciente vira “Caso 01, 02…”, as datas viram mês/ano e só entram os casos com <strong>consentimento do tutor</strong> registrado.</x-alert>
        </aside>
    </div>
@endsection
