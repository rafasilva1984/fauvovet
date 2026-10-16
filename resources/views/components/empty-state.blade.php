@props(['title', 'icon' => null])
<div class="relative flex flex-col items-center gap-3 overflow-hidden rounded-[1.25rem] border border-dashed border-line bg-surface/60 px-6 py-12 text-center">
    <div aria-hidden="true" class="fv-dots pointer-events-none absolute inset-0 text-muted opacity-40"></div>
    @if ($icon)
        <div class="relative flex size-14 items-center justify-center rounded-2xl bg-tint text-link ring-8 ring-tint/40"><x-icon :name="$icon" :size="26" /></div>
    @endif
    <p class="relative font-semibold text-ink">{{ $title }}</p>
    @if (trim($slot))
        <div class="relative max-w-md text-sm text-muted">{{ $slot }}</div>
    @endif
</div>
