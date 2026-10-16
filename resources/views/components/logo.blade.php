@props(['size' => 32])
{{-- Marca FauvoVet: forma própria (pegada estilizada), sem relação com marcas de terceiros. Degradê nas cores da clínica. --}}
@php($gid = 'fvlg'.substr(md5(uniqid('', true)), 0, 6))
<svg viewBox="0 0 40 40" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="var(--fv-primary)" />
            <stop offset="1" stop-color="color-mix(in oklab, var(--fv-primary) 70%, var(--fv-secondary))" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="12" fill="url(#{{ $gid }})" />
    <rect x="0.5" y="0.5" width="39" height="39" rx="11.5" fill="none" stroke="white" stroke-opacity=".18" />
    <g fill="var(--fv-on-primary)">
        <ellipse cx="20" cy="25" rx="7" ry="6" />
        <ellipse cx="11.5" cy="17.5" rx="2.6" ry="3.3" transform="rotate(-18 11.5 17.5)" />
        <ellipse cx="16.5" cy="12" rx="2.6" ry="3.4" transform="rotate(-6 16.5 12)" />
        <ellipse cx="23.5" cy="12" rx="2.6" ry="3.4" transform="rotate(6 23.5 12)" />
        <ellipse cx="28.5" cy="17.5" rx="2.6" ry="3.3" transform="rotate(18 28.5 17.5)" />
    </g>
    <path d="M20 21.5v7M16.5 25h7" stroke="var(--fv-primary)" stroke-width="2.2" stroke-linecap="round" />
</svg>
