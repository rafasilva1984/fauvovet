@props(['title', 'description' => null, 'eyebrow' => null, 'icon' => null])
<div class="mb-7 flex animate-fade-up flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="flex min-w-0 items-start gap-4">
        @if ($icon)
            <span aria-hidden="true" class="hidden size-12 shrink-0 items-center justify-center rounded-2xl bg-brand fv-gradient text-on-brand shadow-glow sm:flex"><x-icon :name="$icon" :size="24" /></span>
        @endif
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="mb-1 text-xs font-bold uppercase tracking-[0.14em] text-link">{{ $eyebrow }}</p>
            @endif
            <h1 class="text-[1.65rem] font-bold leading-tight tracking-tight text-ink sm:text-[2rem]">{{ $title }}</h1>
            @if ($description)
                <p class="mt-1.5 max-w-2xl text-sm text-muted sm:text-[0.95rem]">{{ $description }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot))
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
