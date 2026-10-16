@php($wa = $guardian->phone && strlen($guardian->phone) >= 10 ? 'https://wa.me/55'.$guardian->phone : null)
<div>
    <dt class="text-sm text-muted">Telefone</dt>
    <dd class="flex flex-wrap items-center gap-x-3 font-medium tabular-nums">
        @if ($guardian->phone)
            <a href="tel:+55{{ $guardian->phone }}" class="text-link underline underline-offset-4">{{ \App\Models\Guardian::formatPhone($guardian->phone) }}</a>
            @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="text-sm text-link underline underline-offset-4">WhatsApp</a>@endif
        @else
            —
        @endif
        @if ($guardian->phone_alt)<span class="text-sm text-muted">· {{ \App\Models\Guardian::formatPhone($guardian->phone_alt) }}</span>@endif
    </dd>
</div>
<div><dt class="text-sm text-muted">E-mail</dt><dd class="font-medium break-all">@if ($guardian->email)<a href="mailto:{{ $guardian->email }}" class="text-link underline underline-offset-4">{{ $guardian->email }}</a>@else — @endif</dd></div>
