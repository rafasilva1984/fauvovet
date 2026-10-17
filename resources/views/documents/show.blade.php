@extends('layouts.app')
@section('title', $document->title.' — '.$document->patient->name)

@section('content')
    <x-profile-header :title="$document->title" :eyebrow="\App\Models\DocumentTemplate::CATEGORIES[$document->category].' · '.$document->patient->name" :description="'Emitido em '.$document->issued_at->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i').' por '.$document->issuer->name" avatar-icon="list">
        <x-slot:meta>
            @if ($document->isVoided())
                <x-badge tone="danger" :dot="true">Anulado</x-badge>
            @else
                <x-badge tone="success" :dot="true">Válido</x-badge>
            @endif
            <x-badge>Código {{ $document->verification_code }}</x-badge>
        </x-slot:meta>
        <a href="{{ route('documents.pdf', $document) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand fv-gradient px-4 text-sm font-semibold text-on-brand shadow-glow transition hover:brightness-110"><x-icon name="eye" :size="18" /> Abrir PDF</a>
        <a href="{{ route('documents.pdf', ['document' => $document, 'baixar' => 1]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint">Baixar</a>
    </x-profile-header>

    @if ($document->isVoided())
        <x-alert class="mb-6">Documento anulado em {{ $document->voided_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}. Motivo: {{ $document->void_reason }}. O PDF original continua guardado para histórico.</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card>
            <h2 class="mb-3 text-lg font-bold tracking-tight">Texto emitido</h2>
            <div class="whitespace-pre-line rounded-xl bg-surface-2 p-4 text-sm leading-relaxed">{{ $document->body }}</div>
        </x-card>
        <aside class="flex flex-col gap-4">
            <x-card>
                <dl class="grid gap-3 text-sm">
                    <div><dt class="text-muted">Paciente</dt><dd class="font-semibold"><a class="text-link underline underline-offset-4" href="{{ route('patients.show', $document->patient) }}">{{ $document->patient->name }}</a></dd></div>
                    @if ($document->encounter)
                        <div><dt class="text-muted">Atendimento</dt><dd class="font-semibold"><a class="text-link underline underline-offset-4" href="{{ route('encounters.show', $document->encounter) }}">{{ $document->encounter->started_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</a></dd></div>
                    @endif
                    <div><dt class="text-muted">Profissional</dt><dd class="font-semibold">{{ $document->issuer->name }}{{ $document->issuer->crmv ? ' · '.$document->issuer->crmv : '' }}</dd></div>
                    <div><dt class="text-muted">Integridade (SHA-256)</dt><dd class="break-all font-mono text-xs">{{ $document->pdf_sha256 }}</dd></div>
                </dl>
            </x-card>
            @if (! $document->isVoided() && $fvCan('clinical:write'))
                <x-card>
                    <h2 class="mb-2 font-bold">Anular documento</h2>
                    <form method="POST" action="{{ route('documents.void', $document) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        <x-field name="reason" label="Motivo" required placeholder="Ex.: dose corrigida, emitido outro" />
                        <x-button variant="secondary">Anular</x-button>
                    </form>
                    <p class="mt-2 text-xs text-muted">Só quem emitiu ou o titular pode anular.</p>
                </x-card>
            @endif
        </aside>
    </div>
@endsection
