@extends('layouts.app')
@section('title', $unit->name)

@section('content')
    <x-page-header :title="$unit->name" description="Detalhes da unidade." />
    <x-card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-muted">Cidade/UF</dt><dd class="font-medium">{{ collect([$unit->city, $unit->state])->filter()->join(' · ') ?: '—' }}</dd></div>
            <div><dt class="text-sm text-muted">Fuso horário</dt><dd class="font-medium">{{ $unit->timezone }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-sm text-muted">Endereço</dt><dd class="font-medium">{{ $unit->address_line ?: '—' }}</dd></div>
        </dl>
    </x-card>
    <p class="mt-6"><a href="{{ route('units.index') }}" class="font-semibold text-link underline underline-offset-4">Voltar às unidades</a></p>
@endsection
