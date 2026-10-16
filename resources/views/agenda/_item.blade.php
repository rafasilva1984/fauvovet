@php
    $phone = $a->patient->guardians->first()?->phone;
    $start = $a->starts_at->timezone($tz);
    $end = $a->ends_at->timezone($tz);
@endphp
<div class="flex gap-3 sm:gap-4">
    {{-- Trilho de horário (linha do tempo) --}}
    <div aria-hidden="true" class="hidden w-14 shrink-0 flex-col items-end pt-4 sm:flex">
        <span class="text-base font-bold tabular-nums text-ink">{{ $start->format('H:i') }}</span>
        <span class="text-xs tabular-nums text-muted">{{ $end->format('H:i') }}</span>
    </div>
    <div aria-hidden="true" class="relative hidden w-3 shrink-0 sm:block">
        <span class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-line"></span>
        <span class="absolute left-1/2 top-5 size-3 -translate-x-1/2 rounded-full ring-4 ring-bg {{ in_array($a->status, ['in_progress', 'waiting'], true) ? 'bg-brand' : 'bg-surface-3' }}"></span>
    </div>
    <x-card class="fv-at-{{ strtolower($a->appointment_type_id) }} fv-lift min-w-0 flex-1 border-l-4 p-4 {{ in_array($a->status, ['cancelled', 'no_show'], true) ? 'opacity-75' : '' }}">
        <div class="flex flex-wrap items-start gap-3">
            <x-avatar :name="$a->patient->name" icon="paw" :tone="\App\Support\Species::tone($a->patient->species)" />
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted"><span class="tabular-nums">{{ $start->format('H:i') }}–{{ $end->format('H:i') }}</span> · {{ $a->type->name }}@if ($a->is_walk_in) · encaixe @endif</p>
                <a href="{{ route('agenda.show', $a) }}" class="mt-0.5 block truncate text-base font-bold text-ink hover:text-link">{{ $a->patient->name }}</a>
                <p class="truncate text-sm text-muted">
                    {{ collect([\App\Support\Species::label($a->patient->species), $a->patient->guardians->first()?->name, $phone ? \App\Models\Guardian::formatPhone($phone) : null])->filter()->join(' · ') }}
                </p>
                <p class="mt-1 inline-flex items-center gap-1.5 text-sm text-muted"><x-icon name="stethoscope" :size="15" /> {{ $a->professional?->user?->name ?? 'Sem profissional definido' }}</p>
            </div>
            @include('agenda._status', ['status' => $a->status])
        </div>
        <div class="mt-3 empty:hidden">@include('agenda._actions', ['a' => $a, 'quick' => true, 'back' => 'agenda'])</div>
    </x-card>
</div>
