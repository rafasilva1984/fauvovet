{{-- Rascunho editável. Hipóteses/diagnósticos no mesmo formulário (nada se perde ao salvar). --}}
@php
    $rows = old('diagnoses', $encounter->diagnoses->map(fn ($d) => ['description' => $d->description, 'code' => $d->code, 'status' => $d->status])->all());
    $rows = array_values(is_array($rows) ? $rows : []);
    $rows = array_merge($rows, array_fill(0, max(2, 4 - count($rows)), ['description' => '', 'code' => '', 'status' => 'suspected']));
    $num = fn ($v) => $v === null ? null : rtrim(rtrim(str_replace('.', ',', (string) $v), '0'), ',');
@endphp
<div class="flex flex-col gap-4">
    <x-card>
        <h2 class="mb-3 text-lg font-bold tracking-tight">Queixa e histórico</h2>
        <div class="flex flex-col gap-4">
            <x-textarea name="chief_complaint" label="Queixa principal" :value="$encounter->chief_complaint" rows="3" hint="O motivo da consulta, nas palavras do tutor." />
            <x-textarea name="history" label="Anamnese e histórico" :value="$encounter->history" rows="5" hint="Evolução, alimentação, ambiente, vacinas, medicações em uso, contactantes." />
        </div>
    </x-card>

    <x-card>
        <h2 class="mb-3 text-lg font-bold tracking-tight">Sinais vitais</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <x-field name="weight_kg" label="Peso (kg)" inputmode="decimal" :value="$num($encounter->weight_kg)" placeholder="0,000" />
            <x-field name="temperature_c" label="Temperatura (°C)" inputmode="decimal" :value="$num($encounter->temperature_c)" placeholder="38,5" />
            <x-field name="heart_rate" label="FC (bpm)" type="number" inputmode="numeric" min="10" max="400" :value="$encounter->heart_rate" />
            <x-field name="respiratory_rate" label="FR (mpm)" type="number" inputmode="numeric" min="2" max="200" :value="$encounter->respiratory_rate" />
            <x-field name="capillary_refill_s" label="TPC (s)" inputmode="decimal" :value="$num($encounter->capillary_refill_s)" placeholder="2" />
            <x-select name="body_condition_score" label="Escore corporal" :options="array_combine(range(1, 9), array_map(fn ($i) => $i.'/9', range(1, 9)))" :value="$encounter->body_condition_score" placeholder="—" />
            <x-select name="pain_score" label="Escala de dor" :options="array_combine(range(0, 10), array_map(fn ($i) => $i.'/10', range(0, 10)))" :value="$encounter->pain_score" placeholder="—" />
            <x-select name="mucous_membranes" label="Mucosas" :options="array_combine($mucous, $mucous)" :value="$encounter->mucous_membranes" placeholder="—" />
            <x-select name="hydration" label="Hidratação" :options="array_combine($hydration, $hydration)" :value="$encounter->hydration" placeholder="—" class="col-span-2 sm:col-span-1 lg:col-span-2" />
        </div>
    </x-card>

    <x-card>
        <x-textarea name="physical_exam" label="Exame físico" :value="$encounter->physical_exam" rows="5" hint="Achados por sistema: pele/pelagem, olhos, ouvidos, cavidade oral, linfonodos, cardiorrespiratório, abdome…" />
    </x-card>

    <x-card>
        <h2 class="mb-1 text-lg font-bold tracking-tight">Hipóteses e diagnósticos</h2>
        <p class="mb-3 text-sm text-muted">Suspeita, confirmado ou descartado — nunca confundidos. Linhas vazias são ignoradas.</p>
        @error('record')<x-alert class="mb-3">{{ $message }}</x-alert>@enderror
        <div class="flex flex-col gap-3">
            @foreach ($rows as $i => $row)
                <fieldset class="grid grid-cols-1 gap-2 rounded-xl border border-line p-3 sm:grid-cols-[minmax(0,1fr)_8rem_11rem]">
                    <legend class="sr-only">Hipótese ou diagnóstico {{ $i + 1 }}</legend>
                    <x-field :name="'diagnoses['.$i.'][description]'" :id="'dx-'.$i.'-d'" :label="'Descrição '.($i + 1)" :value="$row['description'] ?? ''" placeholder="Ex.: dermatite atópica" />
                    <x-field :name="'diagnoses['.$i.'][code]'" :id="'dx-'.$i.'-c'" label="Código" :value="$row['code'] ?? ''" />
                    <x-select :name="'diagnoses['.$i.'][status]'" :id="'dx-'.$i.'-s'" label="Situação" :options="\App\Models\EncounterDiagnosis::STATUS" :value="$row['status'] ?? 'suspected'" />
                </fieldset>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">Precisa de mais linhas? Salve o rascunho: novas linhas em branco aparecem.</p>
    </x-card>

    <x-card>
        <div class="flex flex-col gap-4">
            <x-textarea name="plan" label="Conduta e plano terapêutico" :value="$encounter->plan" rows="4" hint="Exames solicitados, tratamento, procedimentos, retorno." />
            <x-textarea name="guidance" label="Orientações ao tutor" :value="$encounter->guidance" rows="3" />
        </div>
    </x-card>
</div>
