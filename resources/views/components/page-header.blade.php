@props(['title', 'description' => null])
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-[1.7rem]">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-muted">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
