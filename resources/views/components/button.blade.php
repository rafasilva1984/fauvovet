@props(['variant' => 'primary', 'type' => 'submit', 'size' => 'md'])
@php
    $variants = [
        // bg-brand mantém a cor da clínica como background-color (o degradê fica por cima, como imagem).
        'primary' => 'bg-brand fv-gradient text-on-brand shadow-glow hover:brightness-110 active:brightness-95',
        'secondary' => 'bg-surface text-ink border border-line shadow-card hover:border-brand/40 hover:bg-tint',
        'ghost' => 'text-ink hover:bg-surface-2',
        'soft' => 'bg-tint text-link hover:bg-tint-strong',
    ];
    $sizes = ['md' => 'min-h-11 px-4 text-sm', 'lg' => 'min-h-12 px-5 text-base'];
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition duration-150 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100 '.$sizes[$size].' '.$variants[$variant]]) }}>
    {{ $slot }}
</button>
