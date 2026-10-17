@props(['name', 'size' => 20])
{{-- Ícones próprios (traço simples 24×24), sem dependência externa. --}}
<svg {{ $attributes }} viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor"
    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('home')
            <path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V20h14V9.5" /><path d="M10 20v-5h4v5" />
        @break
        @case('building')
            <rect x="4" y="3" width="16" height="18" rx="2" /><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3" />
        @break
        @case('users')
            <circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5" /><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c1.8.7 3 2.4 3.5 5.2" />
        @break
        @case('palette')
            <path d="M12 3a9 9 0 1 0 0 18c1.2 0 1.8-.8 1.8-1.7 0-.5-.2-.9-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1 .8-1.7 1.7-1.7H16a5 5 0 0 0 5-5C21 6.4 17 3 12 3Z" /><circle cx="7.5" cy="11" r="1" /><circle cx="10" cy="7" r="1" /><circle cx="15" cy="7.5" r="1" />
        @break
        @case('logout')
            <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3" /><path d="M10 8l-4 4 4 4M6 12h10" />
        @break
        @case('swap')
            <path d="M7 4 3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7" />
        @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
        @break
        @case('close')
            <path d="M6 6l12 12M18 6 6 18" />
        @break
        @case('shield')
            <path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6L12 3Z" /><path d="m9 12 2 2 4-4" />
        @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
        @break
        @case('eye')
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="3" />
        @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
        @break
        @case('calendar')
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M3.5 10h17M8 3v4M16 3v4" />
        @break
        @case('list')
            <path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" />
        @break
        @case('copy')
            <rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V6a2 2 0 0 1 2-2h9" />
        @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" /><path d="m4 7 8 6 8-6" />
        @break
        @case('paw')
            <circle cx="6.5" cy="10" r="1.8" /><circle cx="10" cy="6" r="1.8" /><circle cx="14" cy="6" r="1.8" /><circle cx="17.5" cy="10" r="1.8" /><path d="M12 11c-3 0-5.5 3.6-5.5 6 0 1.6 1.3 2.5 2.8 2.5 1 0 1.7-.5 2.7-.5s1.7.5 2.7.5c1.5 0 2.8-.9 2.8-2.5 0-2.4-2.5-6-5.5-6Z" />
        @break
        @case('search')
            <circle cx="11" cy="11" r="6.5" /><path d="m20 20-4.2-4.2" />
        @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
        @break
        @case('chevron-left')
            <path d="m15 6-6 6 6 6" />
        @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
        @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
        @break
        @case('alert-circle')
            <circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5h.01" />
        @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" /><path d="m8 12.5 2.8 2.8L16.5 9.5" />
        @break
        @case('info')
            <circle cx="12" cy="12" r="9" /><path d="M12 11v5.5M12 7.5h.01" />
        @break
        @case('sparkles')
            <path d="M12 3.5 13.6 8 18 9.6l-4.4 1.6L12 15.6l-1.6-4.4L6 9.6 10.4 8 12 3.5Z" /><path d="M18.5 15.5 19.2 17.3 21 18l-1.8.7-.7 1.8-.7-1.8L16 18l1.8-.7.7-1.8Z" />
        @break
        @case('phone')
            <path d="M5 4h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 6.1 6.1l1.4-2.3L20 15.5V19a1.5 1.5 0 0 1-1.6 1.5C10.6 20 4 13.4 3.5 5.6A1.5 1.5 0 0 1 5 4Z" />
        @break
        @case('stethoscope')
            <path d="M6 3.5v5a4.5 4.5 0 0 0 9 0v-5" /><path d="M10.5 13v2.5a4.5 4.5 0 0 0 9 0V13" /><circle cx="19.5" cy="10.5" r="2.2" /><path d="M5 3.5h2M14 3.5h2" />
        @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 14.6a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h0a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8v0a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z" />
        @break
        @case('heart')
            <path d="M12 20s-7.5-4.4-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.6-7.5 10-7.5 10Z" />
        @break
        @case('user')
            <circle cx="12" cy="8.5" r="3.8" /><path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
        @break
        @case('syringe')
            <path d="m18 2 4 4M17 7l3-3M19 9 8.5 19.5a2.1 2.1 0 0 1-3 0l-1-1a2.1 2.1 0 0 1 0-3L15 5M9 11l4 4M5 19l-3 3M14 4l6 6" />
        @break
        @case('flask')
            <path d="M9 3h6M10 3v6l-5.4 9.4A1.7 1.7 0 0 0 6.1 21h11.8a1.7 1.7 0 0 0 1.5-2.6L14 9V3" /><path d="M7.6 15h8.8" />
        @break
        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7.5" />
        @break
    @endswitch
</svg>
