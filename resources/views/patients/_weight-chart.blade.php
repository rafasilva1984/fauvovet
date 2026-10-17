{{-- Gráfico de peso (SVG puro, sem biblioteca). $weights em ordem cronológica. --}}
@php
    $w = $weights->map(fn ($x) => (float) $x->weight_kg)->values();
    $n = $w->count();
    $min = $n ? $w->min() : 0;
    $max = $n ? $w->max() : 1;
    $pad = max(($max - $min) * 0.15, 0.1);
    $lo = $min - $pad;
    $hi = $max + $pad;
    $W = 300; $H = 90;
    $pts = $w->map(fn ($v, $i) => [round($n > 1 ? $i * $W / ($n - 1) : $W / 2, 1), round($H - ($v - $lo) / ($hi - $lo) * $H, 1)]);
    $fmt = fn ($v) => rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    $last = $weights->last();
    $prev = $n > 1 ? $w[$n - 2] : null;
@endphp
@if ($n === 0)
    <p class="text-sm text-muted">Nenhum peso registrado. O peso informado ao finalizar um atendimento entra aqui.</p>
@else
    <div class="flex items-end justify-between gap-3">
        <p><span class="text-[1.75rem] font-bold tabular-nums tracking-tight">{{ $fmt((float) $last->weight_kg) }}</span> <span class="text-sm text-muted">kg</span></p>
        <p class="text-right text-xs text-muted">em {{ $last->measured_at->format('d/m/Y') }}
            @if ($prev !== null)
                @php
                    $delta = (float) $last->weight_kg - $prev;
                @endphp
                <br><span class="font-semibold {{ $delta > 0 ? 'text-warning' : ($delta < 0 ? 'text-link' : 'text-muted') }}">{{ $delta > 0 ? '+' : ($delta < 0 ? '−' : '±') }}{{ $fmt(abs($delta)) }} kg desde a anterior</span>
            @endif
        </p>
    </div>
    @if ($n > 1)
        <svg viewBox="-6 -6 {{ $W + 12 }} {{ $H + 12 }}" class="mt-3 h-24 w-full overflow-visible" role="img" aria-label="Evolução do peso: {{ $weights->map(fn ($x) => $x->measured_at->format('d/m/Y').' '.$fmt((float) $x->weight_kg).' kg')->join('; ') }}">
            <polyline fill="none" stroke="var(--fv-primary)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="{{ $pts->map(fn ($p) => $p[0].','.$p[1])->join(' ') }}" />
            @foreach ($pts as $i => $p)
                <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="{{ $i === $n - 1 ? 4.5 : 3 }}" fill="{{ $i === $n - 1 ? 'var(--fv-primary)' : 'var(--fv-surface)' }}" stroke="var(--fv-primary)" stroke-width="2" />
            @endforeach
        </svg>
        <p class="mt-1 flex justify-between text-xs text-muted"><span>{{ $weights->first()->measured_at->format('d/m/Y') }}</span><span>{{ $n }} medidas</span></p>
    @endif
@endif
