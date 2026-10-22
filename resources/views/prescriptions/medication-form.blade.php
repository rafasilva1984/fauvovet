@extends('layouts.app')
@section('title', $medication->exists ? 'Editar '.$medication->name : 'Novo medicamento')

@php
    $num = fn ($v) => $v === null ? null : \App\Models\Medication::num((float) $v);
    $options = fn (array $list) => array_combine($list, $list);
@endphp

@section('content')
    <x-page-header icon="pill" eyebrow="Catálogo de medicamentos" :title="$medication->exists ? 'Editar '.$medication->name : 'Novo medicamento'" />
    <x-card class="max-w-3xl">
        <form method="POST" action="{{ $medication->exists ? route('medications.update', $medication) : route('medications.store') }}" class="flex flex-col gap-5" novalidate>
            @csrf
            @if ($medication->exists) @method('PUT') @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field name="name" label="Nome (como sai na receita)" :value="$medication->name" required placeholder="Ex.: Amoxicilina" />
                <x-field name="active_ingredient" label="Princípio ativo" :value="$medication->active_ingredient" />
            </div>
            <x-field name="drug_class" label="Classe" :value="$medication->drug_class" placeholder="Ex.: penicilinas; anti-inflamatório não esteroidal" hint="Usada para avisar quando o paciente tem alergia registrada à classe (ex.: alergia a penicilina)." />

            <fieldset class="rounded-2xl border border-line p-4">
                <legend class="px-1 text-sm font-semibold">Apresentação</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select name="form" label="Forma" :options="\App\Models\Medication::FORMS" :value="$medication->form" required />
                    <x-field name="concentration_mg" label="Concentração (mg por unidade ou por mL)" inputmode="decimal" :value="$num($medication->concentration_mg)" placeholder="Ex.: 250" hint="Comprimido/cápsula: mg por unidade. Líquidos e injetáveis: mg/mL." class="sm:col-span-2" />
                </div>
                <x-field name="presentation" label="Texto da apresentação na receita" :value="$medication->presentation" class="mt-4" hint="Vazio = gerado da forma e concentração (ex.: “comprimido 250 mg”)." />
            </fieldset>

            <fieldset class="rounded-2xl border border-line p-4">
                <legend class="px-1 text-sm font-semibold">Dose e posologia padrão da clínica</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-field name="dose_mg_kg" label="Dose usual (mg/kg)" inputmode="decimal" :value="$num($medication->dose_mg_kg)" />
                    <x-field name="dose_min_mg_kg" label="Faixa mínima (mg/kg)" inputmode="decimal" :value="$num($medication->dose_min_mg_kg)" />
                    <x-field name="dose_max_mg_kg" label="Faixa máxima (mg/kg)" inputmode="decimal" :value="$num($medication->dose_max_mg_kg)" />
                </div>
                <p class="mt-2 text-xs text-muted">A faixa só gera um aviso na receita; não impede a emissão.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select name="route" label="Via" :options="$options(\App\Models\Medication::ROUTES)" :value="$medication->route" placeholder="—" />
                    <x-select name="frequency" label="Frequência" :options="$options(\App\Models\Medication::FREQUENCIES)" :value="$medication->frequency" placeholder="—" />
                    <x-field name="duration_days" label="Duração (dias)" type="number" min="1" max="365" :value="$medication->duration_days" />
                </div>
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Espécies (nenhuma marcada = todas)</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Support\Species::LABELS as $code => $label)
                        <label class="flex min-h-11 items-center gap-2 rounded-xl border border-line px-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-tint">
                            <input type="checkbox" name="species[]" value="{{ $code }}" class="size-4" @checked(in_array($code, old('species', $medication->species ?? []), true))> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <label class="flex min-h-11 items-start gap-3 text-sm">
                <input type="hidden" name="controlled" value="0">
                <input type="checkbox" name="controlled" value="1" class="mt-0.5 size-4" @checked(old('controlled', $medication->controlled))>
                <span>Sujeito a controle especial <span class="block text-xs text-muted">A receita mostra um aviso: o receituário de controle especial (duas vias) ainda não é emitido pelo sistema.</span></span>
            </label>
            <x-textarea name="notes" label="Observações internas" :value="$medication->notes" rows="2" />
            @if ($medication->exists)
                <label class="flex min-h-11 items-center gap-2 text-sm">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" class="size-4" @checked(old('active', $medication->active))>
                    <span>Ativo (aparece ao montar receitas)</span>
                </label>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row">
                <x-button>Salvar</x-button>
                <a href="{{ route('medications.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Voltar</a>
            </div>
        </form>
    </x-card>
@endsection
