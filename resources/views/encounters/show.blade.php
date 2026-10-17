@extends('layouts.app')
@section('title', 'Atendimento de '.$patient->name)

@php
    $editable = $encounter->isDraft() && $fvCan('clinical:write');
    $when = $encounter->started_at->timezone($tz);
    $speciesText = \App\Support\Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : '');
@endphp

@section('content')
    <x-profile-header :title="$patient->name" :eyebrow="'Atendimento · '.$when->format('d/m/Y H:i')" :description="$speciesText.' · '.$encounter->unit->name" avatar-icon="paw" :tone="\App\Support\Species::tone($patient->species)">
        <x-slot:meta>
            @if ($encounter->isDraft())
                <x-badge tone="warning" :dot="true">Rascunho</x-badge>
            @else
                <x-badge tone="success" :dot="true">Finalizado e assinado</x-badge>
            @endif
            @if ($encounter->type)<x-badge tone="tint">{{ $encounter->type->name }}</x-badge>@endif
            @if ($patient->ageLabel())<x-badge>{{ $patient->ageLabel() }}</x-badge>@endif
        </x-slot:meta>
        <a href="{{ route('patients.show', $patient) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint"><x-icon name="paw" :size="18" /> Ficha do paciente</a>
    </x-profile-header>

    @include('patients._alerts-banner', ['alerts' => $alerts])

    @if ($errors->any() && ! $editable)
        <x-alert class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    @if ($editable)
        <form method="POST" action="{{ route('encounters.update', $encounter) }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate>
            @csrf
            @method('PUT')
            <div>
                @if ($errors->any())<x-alert class="mb-4">{{ $errors->first('confirm') ?: ($errors->first('chief_complaint') ?: 'Revise os campos destacados.') }}</x-alert>@endif
                @include('encounters._form')
            </div>
            <aside class="flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start">
                <x-card>
                    <p class="text-sm text-muted">Rascunho aberto por <strong class="text-ink">{{ $encounter->author?->name ?? '—' }}</strong>. Última alteração: {{ $encounter->updated_at?->timezone($tz)->format('d/m H:i') }}.</p>
                    <x-button variant="secondary" class="mt-4 w-full">Salvar rascunho</x-button>
                </x-card>
                @if ($fvCan('clinical:sign'))
                    <x-card class="border-brand/30 bg-tint">
                        <h2 class="flex items-center gap-2 font-bold"><x-icon name="shield" :size="18" class="text-link" /> Finalizar e assinar</h2>
                        <p class="mt-1 text-sm text-muted">Depois de finalizado, o registro <strong class="text-ink">não pode ser alterado</strong>. Correções entram como adendo, com data e autor.</p>
                        <label class="mt-3 flex items-start gap-3 text-sm">
                            <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-4" @checked(old('confirm'))>
                            <span>Revisei o registro e confirmo as informações como responsável técnico.</span>
                        </label>
                        <x-button name="finalize" value="1" class="mt-4 w-full"><x-icon name="check-circle" :size="18" /> Finalizar e assinar</x-button>
                    </x-card>
                @else
                    <x-alert tone="info">Só o médico-veterinário finaliza e assina. Salve o rascunho para ele revisar.</x-alert>
                @endif
                @include('encounters._previous')
            </aside>
        </form>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex flex-col gap-4">
                @if (! $encounter->isDraft())
                    <div class="flex items-start gap-3 rounded-[1.25rem] border border-success/25 bg-success-bg p-4 text-sm text-success">
                        <x-icon name="shield" :size="20" class="mt-0.5 shrink-0" />
                        <p>Finalizado e assinado por <strong>{{ $encounter->signer?->name ?? '—' }}</strong>@if ($encounter->signer?->crmv) ({{ $encounter->signer->crmv }})@endif em {{ $encounter->finalized_at?->timezone($tz)->format('d/m/Y \à\s H:i') }}. Registro bloqueado para alteração.</p>
                    </div>
                @endif
                @include('encounters._readonly')

                @unless ($encounter->isDraft())
                    <section aria-labelledby="adendos-titulo">
                        <h2 id="adendos-titulo" class="mb-3 mt-2 text-lg font-bold tracking-tight">Adendos</h2>
                        @if ($encounter->addenda->isEmpty())
                            <p class="text-sm text-muted">Nenhum adendo.</p>
                        @else
                            <ol class="flex flex-col gap-3">
                                @foreach ($encounter->addenda as $ad)
                                    <li>
                                        <x-card class="border-l-4 border-l-warning p-4">
                                            <p class="text-xs font-semibold text-muted">{{ $ad->created_at->timezone($tz)->format('d/m/Y H:i') }} · {{ $ad->author?->name }}</p>
                                            <p class="mt-1 whitespace-pre-line">{{ $ad->body }}</p>
                                        </x-card>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        @if ($fvCan('clinical:write'))
                            <x-card class="mt-4">
                                <form method="POST" action="{{ route('encounters.addendum', $encounter) }}" class="flex flex-col gap-3" novalidate>
                                    @csrf
                                    <x-textarea name="body" label="Novo adendo" rows="3" hint="Correção ou complemento. Fica registrado com data e autor; não pode ser editado depois." />
                                    <x-button variant="secondary" class="self-start">Registrar adendo</x-button>
                                </form>
                            </x-card>
                        @endif
                    </section>
                @endunless
            </div>
            <aside class="flex flex-col gap-4">
                @include('encounters._previous')
            </aside>
        </div>
    @endif
@endsection
