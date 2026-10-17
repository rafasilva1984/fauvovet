@extends('layouts.app')
@section('title', $guardian->name)

@php
    $canWrite = $fvCan('patients:write');
    $doc = $guardian->document_number;
    // CPF/CNPJ completo só para quem cadastra; os demais veem mascarado.
    $docShown = $doc === null ? null : ($canWrite ? $doc : str_repeat('•', max(0, strlen($doc) - 4)).substr($doc, -4));
@endphp

@section('content')
    <x-profile-header :title="$guardian->name" eyebrow="Responsável" :description="$guardian->status === 'archived' ? 'Responsável arquivado' : null">
        <x-slot:meta>
            <x-badge tone="tint">{{ $guardian->patients->count() }} {{ $guardian->patients->count() === 1 ? 'paciente' : 'pacientes' }}</x-badge>
            @if ($guardian->phone)<x-badge>{{ \App\Models\Guardian::formatPhone($guardian->phone) }}</x-badge>@endif
        </x-slot:meta>
        @if ($canWrite)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('patients.create', ['responsavel' => $guardian->id]) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="plus" /> Novo paciente</a>
                <a href="{{ route('guardians.edit', $guardian) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Editar</a>
            </div>
        @endif
    </x-profile-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <x-card>
            <h2 class="mb-4 text-lg font-bold tracking-tight">Contato</h2>
            <dl class="grid gap-4">
                @include('guardians._contact', ['guardian' => $guardian])
                <div><dt class="text-sm text-muted">{{ $guardian->person_type === 'PJ' ? 'CNPJ' : 'CPF' }}</dt><dd class="font-medium tabular-nums">{{ $docShown ?? '—' }}</dd></div>
                <div><dt class="text-sm text-muted">Endereço</dt><dd class="font-medium">{{ collect([
                    collect([$guardian->address_line, $guardian->address_number])->filter()->join(', ').($guardian->address_complement ? ' — '.$guardian->address_complement : ''),
                    $guardian->district,
                    collect([$guardian->city, $guardian->state])->filter()->join('/'),
                    $guardian->postal_code ? 'CEP '.substr($guardian->postal_code, 0, 5).'-'.substr($guardian->postal_code, 5) : null,
                ])->filter()->join(' · ') ?: '—' }}</dd></div>
                @if ($guardian->notes)
                    <div><dt class="text-sm text-muted">Observações</dt><dd class="whitespace-pre-line">{{ $guardian->notes }}</dd></div>
                @endif
            </dl>
        </x-card>

        <section aria-labelledby="pacientes-titulo">
            <h2 id="pacientes-titulo" class="mb-3 text-lg font-bold tracking-tight">Pacientes</h2>
            @if ($guardian->patients->isEmpty())
                <x-empty-state title="Nenhum paciente vinculado" icon="paw" />
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($guardian->patients as $pt)
                        <li>@include('patients._card', ['patient' => $pt, 'relationship' => $pt->pivot->relationship ?? null])</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    <p class="mt-6"><a href="{{ route('guardians.index') }}" class="font-semibold text-link underline underline-offset-4">Voltar aos responsáveis</a></p>
@endsection
