@props(['title', 'icon' => null])
<div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-line px-6 py-10 text-center">
    @if ($icon)
        <div class="text-muted"><x-icon :name="$icon" :size="28" /></div>
    @endif
    <p class="font-semibold text-ink">{{ $title }}</p>
    @if (trim($slot))
        <div class="max-w-md text-sm text-muted">{{ $slot }}</div>
    @endif
</div>
