{{-- Valores estruturados do exame ($exam, $rows de ExamService::valueRows). A situação é só a comparação com a faixa
     informada — não é interpretação clínica. --}}
@php
    $F = \App\Models\ExamResultValue::FLAGS;
    $T = \App\Models\ExamResultValue::TONE;
    $fmt = fn ($v) => \App\Models\ExamResultValue::fmt($v);
    $editable = $fvCan('clinical:write') && $exam->isOpen();
    $tz = 'America/Sao_Paulo';
    $flagged = $rows->filter(fn ($r) => in_array($r['value']?->flag, ['low', 'high'], true))->count();
@endphp
<x-card>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">Valores do exame</h2>
        @if ($flagged > 0)<x-badge tone="danger" :dot="true">{{ $flagged }} fora da faixa</x-badge>@endif
    </div>
    @if ($rows->isEmpty())
        <p class="text-sm text-muted">Este exame não tem parâmetros cadastrados.
            @if ($fvCan('exams:configure'))<a href="{{ route('exam-types.show', $exam->exam_type_id) }}" class="font-semibold text-link underline underline-offset-4">Cadastrar parâmetros</a>@endif
        </p>
    @elseif (! $editable)
        <ul class="divide-y divide-line">
            @foreach ($rows as $r)
                @php($v = $r['value'])
                @php($prev = $v ? $r['history']->filter(fn ($h) => $h->measured_at->lt($v->measured_at) && $h->id !== $v->id)->last() : null)
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                    <div class="min-w-36 flex-1">
                        <p class="font-semibold">{{ $r['analyte']->name }}</p>
                        <p class="text-xs text-muted">Referência: {{ $v ? $v->rangeLabel() : '—' }}{{ $r['analyte']->unit ? ' '.$r['analyte']->unit : '' }}</p>
                    </div>
                    <p class="min-w-24 text-right"><span class="text-lg font-bold tabular-nums">{{ $v ? $v->display() : '—' }}</span> <span class="text-xs text-muted">{{ $v?->unit }}</span>
                        @if ($prev)<span class="block text-xs text-muted">anterior {{ $prev->display() }} em {{ $prev->measured_at->timezone($tz)->format('d/m/Y') }}</span>@endif
                    </p>
                    @if ($v)<x-badge :tone="$T[$v->flag]">{{ $F[$v->flag] }}</x-badge>@endif
                    @include('exams._sparkline', ['history' => $r['history'], 'current' => $v])
                </li>
            @endforeach
        </ul>
    @else
        <form method="POST" action="{{ route('exams.values', $exam) }}" class="flex flex-col gap-3" novalidate>
            @csrf
            <p class="text-xs text-muted">A faixa vem do catálogo para {{ \App\Support\Species::label($exam->patient->species) }}; ajuste para a faixa impressa no laudo, se for diferente. Valor não numérico (ex.: “negativo”) é aceito, sem comparação.</p>
            <ul class="divide-y divide-line">
                @foreach ($rows as $r)
                    @php($a = $r['analyte']) @php($v = $r['value']) @php($s = $r['suggested'])
                    <li class="grid grid-cols-3 items-end gap-3 py-3 sm:grid-cols-[minmax(0,1.25fr)_repeat(3,minmax(0,1fr))]">
                        <div class="col-span-3 flex flex-wrap items-center gap-x-3 gap-y-1 sm:col-span-1 sm:self-center">
                            <p class="font-semibold">{{ $a->name }}<span class="block text-xs font-normal text-muted">{{ $a->unit ?: 'sem unidade' }}</span></p>
                            @if ($v)<x-badge :tone="$T[$v->flag]">{{ $F[$v->flag] }}</x-badge>@endif
                            @include('exams._sparkline', ['history' => $r['history'], 'current' => $v])
                        </div>
                        <x-field :name="'values['.$a->id.'][value]'" :id="'v-'.$a->id" label="Resultado" inputmode="decimal" :value="$v?->display()" />
                        <x-field :name="'values['.$a->id.'][ref_min]'" :id="'mn-'.$a->id" label="Ref. mín." inputmode="decimal" :value="$fmt($v ? $v->ref_min : $s?->ref_min)" />
                        <x-field :name="'values['.$a->id.'][ref_max]'" :id="'mx-'.$a->id" label="Ref. máx." inputmode="decimal" :value="$fmt($v ? $v->ref_max : $s?->ref_max)" />
                    </li>
                @endforeach
            </ul>
            <x-button variant="secondary">Salvar valores</x-button>
        </form>
    @endif
</x-card>
