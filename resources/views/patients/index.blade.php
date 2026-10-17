@extends('layouts.app')
@section('title', 'Pacientes')

@section('content')
    @php
        $clinical = $fvCan('clinical:read');
        $dxTone = ['confirmed' => 'danger', 'suspected' => 'warning', 'ruled_out' => 'neutral'];
        $ages = ['' => 'Qualquer'] + collect(range(0, 30))->mapWithKeys(fn ($n) => [$n => $n.' '.($n === 1 ? 'ano' : 'anos')])->all();
        $chips = array_filter([
            'raca' => $f['raca'] !== '' ? 'Raça: '.$f['raca'] : null,
            'sexo' => $f['sexo'] !== '' ? \App\Models\Patient::SEX[$f['sexo']] : null,
            'castrado' => $f['castrado'] !== '' ? \App\Models\Patient::NEUTERED[$f['castrado']] : null,
            'idade' => $f['idade_min'] !== null || $f['idade_max'] !== null
                ? 'Idade: '.($f['idade_min'] ?? 0).($f['idade_max'] !== null ? ' a '.$f['idade_max'] : ' ou mais').' anos' : null,
            'diagnostico' => $f['diagnostico'] !== '' ? 'Diagnóstico: '.$f['diagnostico'] : null,
            'situacao' => $f['situacao'] !== '' ? \App\Services\PatientSearch::DX_STATUS[$f['situacao']] : null,
            'ano' => $f['ano'] !== null ? 'Atendido em '.$f['ano'] : null,
        ]);
    @endphp
    <x-page-header title="Pacientes" icon="paw" eyebrow="Cadastro" description="Animais atendidos por esta clínica, de todas as espécies.">
        @if ($fvCan('patients:write'))
            <a href="{{ route('patients.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo paciente</a>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('patients.index') }}" class="mb-6 rounded-[1.25rem] border border-line bg-surface p-4 shadow-card" role="search" aria-label="Buscar pacientes">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end">
            <x-field name="q" label="Buscar por paciente, responsável, telefone ou microchip" type="search" :value="$q" autocomplete="off" />
            <x-select name="especie" label="Espécie" :options="\App\Support\Species::LABELS" :value="$species" placeholder="Todas" />
            <x-button variant="secondary">Buscar</x-button>
        </div>

        <details class="group mt-3 border-t border-line pt-3" @if ($advanced && $patients->isEmpty()) open @endif>
            <summary class="inline-flex min-h-11 cursor-pointer items-center gap-2 font-semibold text-link">
                <x-icon name="chevron-right" :size="16" class="transition group-open:rotate-90" /> Filtros avançados
                @if ($chips !== [])<x-badge tone="tint">{{ count($chips) }}</x-badge>@endif
            </summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-field name="raca" label="Raça" :value="$f['raca']" autocomplete="off" class="lg:col-span-2" />
                <x-select name="sexo" label="Sexo" :options="\App\Models\Patient::SEX" :value="$f['sexo']" placeholder="Todos" />
                <x-select name="idade_min" label="Idade mínima" :options="$ages" :value="$f['idade_min']" />
                <x-select name="idade_max" label="Idade máxima" :options="$ages" :value="$f['idade_max']" />
                <x-select name="castrado" label="Castração" :options="\App\Models\Patient::NEUTERED" :value="$f['castrado']" placeholder="Todos" class="lg:col-span-2" />
            </div>
            @if ($clinical)
                <fieldset class="mt-4 rounded-xl border border-line bg-surface-2/50 p-3">
                    <legend class="px-1 text-sm font-bold text-ink">Prontuário</legend>
                    <p class="mb-3 text-xs text-muted">Considera somente atendimentos finalizados. A situação do diagnóstico aparece em cada resultado.</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <x-field name="diagnostico" label="Diagnóstico ou código" :value="$f['diagnostico']" autocomplete="off" class="lg:col-span-2" hint="Ex.: dermatite, doença renal" />
                        <x-select name="situacao" label="Situação" :options="\App\Services\PatientSearch::DX_STATUS" :value="$f['situacao']" class="lg:col-span-2" />
                        <x-select name="ano" label="Ano de atendimento" :options="collect($years)->mapWithKeys(fn ($y) => [$y => (string) $y])->all()" :value="$f['ano']" placeholder="Qualquer" />
                    </div>
                </fieldset>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-button variant="secondary">Aplicar filtros</x-button>
                @if ($advanced || $q !== '' || $species !== '')
                    <a href="{{ route('patients.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-muted underline-offset-4 hover:text-ink hover:underline">Limpar tudo</a>
                @endif
            </div>
        </details>

        <label class="mt-2 flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" name="todos" value="1" class="size-4" @checked($f['todos'])>
            <span>Incluir óbitos e arquivados</span>
        </label>
    </form>

    @if ($patients->isEmpty())
        <x-empty-state :title="$q === '' && $species === '' && ! $advanced ? 'Nenhum paciente cadastrado' : 'Nenhum paciente encontrado'" icon="paw">
            @if ($advanced) Tente remover algum filtro avançado. @elseif ($q !== '') Tente parte do nome, do responsável ou os últimos dígitos do telefone. @endif
        </x-empty-state>
    @else
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <p class="text-sm font-semibold text-muted">{{ $patients->total() }} {{ $patients->total() === 1 ? 'paciente' : 'pacientes' }}</p>
            @foreach ($chips as $chip)
                <x-badge tone="tint">{{ $chip }}</x-badge>
            @endforeach
        </div>
        <ul class="grid grid-cols-1 gap-3 xl:grid-cols-2">
            @foreach ($patients as $pt)
                <li>
                    @include('patients._card', ['patient' => $pt, 'showGuardians' => true])
                    @if (! empty($evidence[$pt->id]))
                        <ul class="mt-2 flex flex-col gap-1 pl-4" aria-label="Diagnósticos encontrados para {{ $pt->name }}">
                            @foreach ($evidence[$pt->id] as $ev)
                                <li>
                                    <a href="{{ route('encounters.show', $ev['encounter_id']) }}" class="inline-flex min-h-8 flex-wrap items-center gap-2 text-sm text-ink hover:text-link hover:underline">
                                        <x-badge :tone="$dxTone[$ev['status']]" :dot="true">{{ \App\Models\EncounterDiagnosis::STATUS[$ev['status']] }}</x-badge>
                                        <span class="font-semibold">{{ $ev['description'] }}@if ($ev['code']) <span class="font-normal text-muted">({{ $ev['code'] }})</span>@endif</span>
                                        <span class="text-muted">atendimento de {{ $ev['date']->timezone('America/Sao_Paulo')->format('d/m/Y') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $patients->links('partials.pagination') }}</div>
    @endif
@endsection
