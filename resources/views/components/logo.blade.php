@props(['size' => 32])
{{-- Marca FauvoVet: forma própria (pegada estilizada), sem relação com marcas de terceiros. --}}
<svg viewBox="0 0 40 40" width="{{ $size }}" height="{{ $size }}" aria-hidden="true" focusable="false" {{ $attributes }}>
    <rect width="40" height="40" rx="11" fill="var(--fv-primary)" />
    <g fill="var(--fv-on-primary)">
        <ellipse cx="20" cy="25" rx="7" ry="6" />
        <ellipse cx="11.5" cy="17.5" rx="2.6" ry="3.3" transform="rotate(-18 11.5 17.5)" />
        <ellipse cx="16.5" cy="12" rx="2.6" ry="3.4" transform="rotate(-6 16.5 12)" />
        <ellipse cx="23.5" cy="12" rx="2.6" ry="3.4" transform="rotate(6 23.5 12)" />
        <ellipse cx="28.5" cy="17.5" rx="2.6" ry="3.3" transform="rotate(18 28.5 17.5)" />
    </g>
    <path d="M20 21.5v7M16.5 25h7" stroke="var(--fv-primary)" stroke-width="2.2" stroke-linecap="round" />
</svg>
