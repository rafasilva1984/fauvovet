@php
    $tones = [
        'scheduled' => 'bg-surface-2 text-ink',
        'confirmed' => 'bg-surface-2 text-link ring-1 ring-inset ring-current',
        'waiting' => 'bg-warning-bg text-warning',
        'in_progress' => 'bg-brand text-on-brand',
        'completed' => 'bg-success-bg text-success',
        'no_show' => 'bg-danger-bg text-danger',
        'cancelled' => 'bg-surface-2 text-muted line-through',
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tones[$status] ?? '' }}">{{ \App\Models\Appointment::STATUS[$status] ?? $status }}</span>
