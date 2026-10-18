{{-- Mini gráfico comparativo (SVG puro): $history (ExamResultValue em ordem cronológica, numéricos), faixa do valor atual
     sombreada, ponto atual destacado. --}}
@php
    $pts = $history->filter(fn ($v) => $v->value !== null)->values();
    $n = $pts->count();
@endphp
@if ($n >= 2)
    @php
        $vals = $pts->map(fn ($v) => (float) $v->value);
        $refMin = $current?->ref_min !== null ? (float) $current->ref_min : null;
        $refMax = $current?->ref_max !== null ? (float) $current->ref_max : null;
        $lo = min($vals->min(), $refMin ?? INF);
        $hi = max($vals->max(), $refMax ?? -INF);
        $pad = max(($hi - $lo) * 0.15, 0.01);
        $lo -= $pad; $hi += $pad;
        $W = 160; $H = 40;
        $y = fn ($v) => round($H - ($v - $lo) / ($hi - $lo) * $H, 1);
        $xy = $vals->map(fn ($v, $i) => [round($i * $W / ($n - 1), 1), $y($v)]);
        $label = $pts->map(fn ($v) => $v->measured_at->timezone('America/Sao_Paulo')->format('d/m/Y').': '.\App\Models\ExamResultValue::fmt($v->value))->join('; ');
    @endphp
    <svg viewBox="-4 -4 {{ $W + 8 }} {{ $H + 8 }}" class="h-11 w-40 overflow-visible" role="img" aria-label="Evolução: {{ $label }}">
        @if ($refMin !== null || $refMax !== null)
            @php($top = $y($refMax ?? $hi)) @php($bottom = $y($refMin ?? $lo))
            <rect x="0" y="{{ $top }}" width="{{ $W }}" height="{{ max(0, $bottom - $top) }}" fill="var(--fv-primary)" opacity="0.10" />
        @endif
        <polyline fill="none" stroke="var(--fv-primary)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="{{ $xy->map(fn ($p) => $p[0].','.$p[1])->join(' ') }}" />
        @foreach ($xy as $i => $p)
            <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="{{ $current && $pts[$i]->id === $current->id ? 4 : 2.5 }}" fill="{{ $current && $pts[$i]->id === $current->id ? 'var(--fv-primary)' : 'var(--fv-surface)' }}" stroke="var(--fv-primary)" stroke-width="1.5" />
        @endforeach
    </svg>
@endif
