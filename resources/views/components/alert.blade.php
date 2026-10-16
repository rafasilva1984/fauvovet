@props(['tone' => 'danger'])
@php
    $tones = [
        'danger' => ['bg-danger-bg text-danger border-danger/20', 'alert-circle'],
        'success' => ['bg-success-bg text-success border-success/20', 'check-circle'],
        'warning' => ['bg-warning-bg text-warning border-warning/25', 'alert-circle'],
        'info' => ['bg-tint text-ink border-line', 'info'],
    ];
    [$classes, $icon] = $tones[$tone] ?? $tones['info'];
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm animate-fade-up '.$classes]) }}>
    <x-icon :name="$icon" :size="18" class="mt-0.5 shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
