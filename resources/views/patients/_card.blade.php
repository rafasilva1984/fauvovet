<a href="{{ route('patients.show', $patient) }}" class="block">
    <x-card class="flex items-start gap-4 transition hover:border-brand/40">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-link"><x-icon name="paw" /></span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2">
                <span class="font-semibold">{{ $patient->name }}</span>
                @if ($patient->status !== 'active')<x-badge>{{ \App\Models\Patient::STATUS[$patient->status] }}</x-badge>@endif
            </span>
            <span class="block text-sm text-muted">
                {{ collect([\App\Support\Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : ''), $patient->breed, $patient->ageLabel()])->filter()->join(' · ') }}
            </span>
            @isset($showGuardians)
                @if ($patient->guardians->isNotEmpty())
                    <span class="mt-0.5 block truncate text-sm text-muted">Resp.: {{ $patient->guardians->pluck('name')->join(', ') }}</span>
                @endif
            @endisset
            @if (! empty($relationship))
                <span class="mt-0.5 block text-xs text-muted">{{ \App\Models\Patient::RELATIONSHIPS[$relationship] ?? '' }}</span>
            @endif
        </span>
    </x-card>
</a>
