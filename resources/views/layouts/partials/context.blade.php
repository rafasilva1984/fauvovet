<div class="flex items-center gap-3 rounded-2xl bg-tint p-3">
    <x-avatar :name="$tenantName" size="sm" />
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-ink" title="{{ $tenantName }}">{{ $tenantName }}</p>
        <p class="truncate text-xs text-muted">{{ $unitLabel }}</p>
    </div>
    <a href="{{ route('context.show') }}" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl px-2 text-xs font-semibold text-link hover:bg-tint-strong">
        <x-icon name="swap" :size="16" /> Trocar clínica/unidade
    </a>
</div>
