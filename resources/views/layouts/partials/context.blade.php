<div class="rounded-xl bg-surface-2 p-3">
    <p class="truncate text-sm font-semibold text-ink" title="{{ $tenantName }}">{{ $tenantName }}</p>
    <p class="truncate text-xs text-muted">{{ $unitLabel }}</p>
    <a href="{{ route('context.show') }}" class="mt-2 inline-flex min-h-8 items-center gap-1.5 text-xs font-semibold text-link">
        <x-icon name="swap" :size="16" /> Trocar clínica/unidade
    </a>
</div>
