@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'neutral' => 'bg-surface-2 text-muted ring-line',
        'brand' => 'bg-brand fv-gradient text-on-brand ring-transparent',
        'tint' => 'bg-tint text-link ring-transparent',
        'success' => 'bg-success-bg text-success ring-transparent',
        'warning' => 'bg-warning-bg text-warning ring-transparent',
        'danger' => 'bg-danger-bg text-danger ring-transparent',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($tones[$tone] ?? $tones['neutral'])]) }}>@if ($dot)<span aria-hidden="true" class="size-1.5 rounded-full bg-current"></span>@endif{{ $slot }}</span>
