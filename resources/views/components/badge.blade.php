@props(['tone' => 'neutral'])
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold '.($tone === 'brand' ? 'bg-brand text-on-brand' : 'bg-surface-2 text-muted')]) }}>{{ $slot }}</span>
