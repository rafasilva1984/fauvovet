@props(['hover' => false])
<div {{ $attributes->merge(['class' => 'rounded-[1.25rem] border border-line bg-surface p-5 shadow-card'.($hover ? ' fv-lift' : '')]) }}>{{ $slot }}</div>
