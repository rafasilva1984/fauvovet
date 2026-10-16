{{-- Cabeçalho de perfil (paciente, responsável, membro...): avatar grande, título, metadados e ações. --}}
@props(['title', 'eyebrow' => null, 'description' => null, 'avatarName' => null, 'avatarIcon' => null, 'tone' => null])
<section class="relative mb-7 animate-fade-up overflow-hidden rounded-[1.5rem] border border-line bg-surface bg-linear-to-br from-tint via-surface to-surface p-5 shadow-card sm:p-6">
    <div aria-hidden="true" class="fv-dots pointer-events-none absolute -right-4 -top-4 h-28 w-56 text-link opacity-25"></div>
    <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <x-avatar :name="$avatarName ?? $title" :icon="$avatarIcon" :tone="$tone" size="xl" class="shadow-card ring-4 ring-surface" />
            <div class="min-w-0">
                @if ($eyebrow)
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-link">{{ $eyebrow }}</p>
                @endif
                <h1 class="truncate text-[1.6rem] font-bold leading-tight tracking-tight text-ink sm:text-[1.9rem]">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
                @isset($meta)
                    <div class="mt-2 flex flex-wrap gap-1.5">{{ $meta }}</div>
                @endisset
            </div>
        </div>
        @if (trim($slot))
            <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
        @endif
    </div>
</section>
