<x-card>
    <h2 class="mb-3 font-bold">Atendimentos anteriores</h2>
    @if ($previous->isEmpty())
        <p class="text-sm text-muted">Primeiro atendimento registrado deste paciente.</p>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($previous as $p)
                <li>
                    <a href="{{ route('encounters.show', $p) }}" class="block rounded-xl px-3 py-2 text-sm transition hover:bg-tint">
                        <span class="font-semibold tabular-nums">{{ $p->started_at->timezone($tz)->format('d/m/Y') }}</span>
                        <span class="block truncate text-muted">{{ $p->chief_complaint ?: ($p->diagnoses->first()?->description ?? 'Atendimento') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
