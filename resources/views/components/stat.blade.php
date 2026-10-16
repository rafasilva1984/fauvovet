{{-- Indicador do painel: ícone em degradê, número grande e rótulo. Compacto (vertical) no celular. --}}
@props(['value', 'label', 'icon', 'href' => null, 'hint' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group relative flex flex-col gap-3 overflow-hidden rounded-[1.25rem] border border-line bg-surface p-4 shadow-card sm:flex-row sm:items-center sm:gap-4 sm:p-5'.($href ? ' fv-lift' : '')]) }}>
    <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-12 size-32 rounded-full bg-tint opacity-70 blur-2xl"></span>
    <span class="relative flex size-10 shrink-0 items-center justify-center rounded-2xl bg-brand fv-gradient text-on-brand shadow-glow sm:size-12"><x-icon :name="$icon" :size="20" /></span>
    <span class="relative min-w-0">
        <span class="block text-[1.6rem] font-bold leading-none tracking-tight tabular-nums text-ink sm:text-[1.75rem]">{{ $value }}</span>
        <span class="mt-1 block text-sm leading-snug text-muted">{{ $label }}</span>
        @if ($hint)<span class="mt-0.5 block text-xs font-semibold text-link">{{ $hint }}</span>@endif
    </span>
    @if ($href)
        <x-icon name="chevron-right" :size="18" class="relative ml-auto hidden shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-link sm:block" />
    @endif
</{{ $tag }}>
