@props(['tone' => 'danger'])
@php
    $tones = [
        'danger' => 'bg-danger-bg text-danger border-danger/25',
        'success' => 'bg-success-bg text-success border-success/25',
        'warning' => 'bg-warning-bg text-warning border-warning/25',
        'info' => 'bg-surface-2 text-ink border-line',
    ];
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.$tones[$tone]]) }}>
    {{ $slot }}
</div>
