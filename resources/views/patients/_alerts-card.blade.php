{{-- Alertas clínicos ativos + registro de novo alerta. --}}
<x-card>
    <h3 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="alert-circle" :size="18" class="text-danger" /> Alertas clínicos</h3>
    @if ($clinical['alerts']->isEmpty())
        <p class="text-sm text-muted">Nenhum alerta ativo.</p>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($clinical['alerts'] as $al)
                <li class="flex items-start justify-between gap-2 rounded-xl bg-surface-2 px-3 py-2">
                    <span class="min-w-0 text-sm"><strong class="font-semibold">{{ \App\Models\PatientAlert::KINDS[$al->kind] }}</strong> · {{ $al->description }} <span class="text-xs text-muted">(gravidade {{ mb_strtolower(\App\Models\PatientAlert::SEVERITY[$al->severity]) }})</span></span>
                    @if ($fvCan('clinical:write'))
                        <form method="POST" action="{{ route('alerts.resolve', $al) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-link hover:bg-tint">Resolver<span class="sr-only"> alerta {{ $al->description }}</span></button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($fvCan('clinical:write'))
        <form method="POST" action="{{ route('alerts.store', $patient) }}" class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4" novalidate>
            @csrf
            <x-select name="kind" label="Tipo" :options="\App\Models\PatientAlert::KINDS" value="allergy" />
            <x-select name="severity" label="Gravidade" :options="\App\Models\PatientAlert::SEVERITY" value="high" />
            <x-field name="description" label="Descrição do alerta" required class="col-span-2" placeholder="Ex.: alergia a penicilina" />
            <x-button variant="secondary" class="col-span-2">Adicionar alerta</x-button>
        </form>
    @endif
</x-card>
