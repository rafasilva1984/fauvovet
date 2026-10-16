@php
    $tones = [
        'scheduled' => 'bg-surface-2 text-muted ring-line',
        'confirmed' => 'bg-tint text-link ring-transparent',
        'waiting' => 'bg-warning-bg text-warning ring-transparent',
        'in_progress' => 'bg-brand fv-gradient text-on-brand ring-transparent',
        'completed' => 'bg-success-bg text-success ring-transparent',
        'no_show' => 'bg-danger-bg text-danger ring-transparent',
        'cancelled' => 'bg-surface-2 text-muted ring-line line-through',
    ];
@endphp
<span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tones[$status] ?? '' }}"><span aria-hidden="true" class="size-1.5 rounded-full bg-current {{ $status === 'waiting' ? 'animate-pulse' : '' }}"></span>{{ \App\Models\Appointment::STATUS[$status] ?? $status }}</span>
