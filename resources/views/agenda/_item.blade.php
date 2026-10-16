@php
    $phone = $a->patient->guardians->first()?->phone;
@endphp
<x-card class="fv-at-{{ strtolower($a->appointment_type_id) }} flex flex-col gap-3 border-l-4 p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-sm font-semibold tabular-nums">{{ $a->starts_at->timezone($tz)->format('H:i') }}–{{ $a->ends_at->timezone($tz)->format('H:i') }} · {{ $a->type->name }}@if ($a->is_walk_in) <span class="text-muted">· encaixe</span>@endif</p>
            <a href="{{ route('agenda.show', $a) }}" class="mt-0.5 block truncate text-base font-semibold text-link underline underline-offset-4">{{ $a->patient->name }}</a>
            <p class="truncate text-sm text-muted">
                {{ collect([\App\Support\Species::label($a->patient->species), $a->patient->guardians->first()?->name, $phone ? \App\Models\Guardian::formatPhone($phone) : null])->filter()->join(' · ') }}
            </p>
            <p class="text-sm text-muted">{{ $a->professional?->user?->name ?? 'Sem profissional definido' }}</p>
        </div>
        @include('agenda._status', ['status' => $a->status])
    </div>
    @include('agenda._actions', ['a' => $a, 'quick' => true, 'back' => 'agenda'])
</x-card>
