<a href="{{ route('patients.show', $patient) }}" class="group block">
    <x-card :hover="true" class="flex items-center gap-4 p-4">
        <x-avatar :name="$patient->name" icon="paw" :tone="\App\Support\Species::tone($patient->species)" />
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="font-bold text-ink">{{ $patient->name }}</span>
                @if ($patient->status !== 'active')<x-badge :tone="$patient->status === 'deceased' ? 'neutral' : 'warning'">{{ \App\Models\Patient::STATUS[$patient->status] }}</x-badge>@endif
            </span>
            <span class="block truncate text-sm text-muted">
                {{ collect([\App\Support\Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : ''), $patient->breed, $patient->ageLabel()])->filter()->join(' · ') }}
            </span>
            @isset($showGuardians)
                @if ($patient->guardians->isNotEmpty())
                    <span class="mt-0.5 flex items-center gap-1 truncate text-sm text-muted"><x-icon name="user" :size="14" class="shrink-0" /> <span class="truncate">{{ $patient->guardians->pluck('name')->join(', ') }}</span></span>
                @endif
            @endisset
            @if (! empty($relationship))
                <span class="mt-1 inline-block"><x-badge tone="tint">{{ \App\Models\Patient::RELATIONSHIPS[$relationship] ?? '' }}</x-badge></span>
            @endif
        </span>
        <x-icon name="chevron-right" :size="18" class="shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-link" />
    </x-card>
</a>
