<x-card>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="font-bold">Documentos deste atendimento</h2>
        <div class="flex flex-wrap justify-end gap-1">
            @if ($fvCan('clinical:sign'))
                <a href="{{ route('prescriptions.create', ['patient' => $encounter->patient_id, 'atendimento' => $encounter->id]) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-link hover:bg-tint"><x-icon name="pill" :size="16" /> Receita</a>
            @endif
            @if ($fvCan('clinical:write'))
                <a href="{{ route('documents.create', ['patient' => $encounter->patient_id, 'atendimento' => $encounter->id]) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-link hover:bg-tint"><x-icon name="plus" :size="16" /> Emitir</a>
            @endif
        </div>
    </div>
    @include('documents._list', ['documents' => $documents])
</x-card>
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
