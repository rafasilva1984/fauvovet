@props(['variant' => 'primary', 'type' => 'submit'])
@php
    $variants = [
        'primary' => 'bg-brand text-on-brand hover:brightness-110 active:brightness-95 shadow-sm',
        'secondary' => 'bg-surface text-ink border border-line hover:bg-surface-2',
        'ghost' => 'text-ink hover:bg-surface-2',
    ];
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 '.$variants[$variant]]) }}>
    {{ $slot }}
</button>
