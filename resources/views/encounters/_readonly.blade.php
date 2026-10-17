{{-- Registro em modo leitura (finalizado, ou rascunho para quem só lê). --}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $vitals = collect(\App\Models\Encounter::VITALS)->filter(fn ($meta, $f) => $encounter->{$f} !== null && $encounter->{$f} !== '');
@endphp
<div class="flex flex-col gap-4">
    @if ($vitals->isNotEmpty())
        <x-card>
            <h2 class="mb-3 text-lg font-bold tracking-tight">Sinais vitais</h2>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($vitals as $f => [$label, $unit])
                    <div class="rounded-xl bg-surface-2 px-3 py-2">
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                        <dd class="font-bold tabular-nums">{{ is_numeric($encounter->{$f}) ? $fmt($encounter->{$f}) : $encounter->{$f} }} <span class="text-xs font-semibold text-muted">{{ $unit }}</span></dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    @endif
    @foreach (\App\Models\Encounter::TEXT_FIELDS as $f => $label)
        @if (trim((string) $encounter->{$f}) !== '')
            <x-card>
                <h2 class="mb-2 text-lg font-bold tracking-tight">{{ $label }}</h2>
                <p class="whitespace-pre-line">{{ $encounter->{$f} }}</p>
            </x-card>
        @endif
        @if ($f === 'physical_exam' && $encounter->diagnoses->isNotEmpty())
            <x-card>
                <h2 class="mb-3 text-lg font-bold tracking-tight">Hipóteses e diagnósticos</h2>
                <ul class="flex flex-col gap-2">
                    @foreach ($encounter->diagnoses as $d)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-badge :tone="['confirmed' => 'danger', 'suspected' => 'warning', 'ruled_out' => 'neutral'][$d->status]" :dot="true">{{ \App\Models\EncounterDiagnosis::STATUS[$d->status] }}</x-badge>
                            <span class="font-semibold">{{ $d->description }}</span>
                            @if ($d->code)<span class="text-xs text-muted">({{ $d->code }})</span>@endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    @endforeach
</div>
