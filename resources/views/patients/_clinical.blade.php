{{-- Prontuário na ficha do paciente (somente equipe clínica; acesso auditado no controller). --}}
<section aria-labelledby="prontuario-titulo" class="mt-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="prontuario-titulo" class="flex items-center gap-2 text-xl font-bold tracking-tight"><span class="flex size-9 items-center justify-center rounded-xl bg-brand fv-gradient text-on-brand"><x-icon name="stethoscope" :size="18" /></span> Prontuário</h2>
        @if ($fvCan('clinical:write') && $patient->status === 'active')
            <form method="POST" action="{{ route('encounters.store', $patient) }}">
                @csrf
                <x-button><x-icon name="plus" :size="18" /> Novo atendimento</x-button>
            </form>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div>
            @if ($clinical['encounters']->isEmpty())
                <x-empty-state title="Nenhum atendimento registrado" icon="stethoscope">Use “Iniciar atendimento” na agenda ou “Novo atendimento” acima.</x-empty-state>
            @else
                <ol class="relative flex flex-col gap-3">
                    @foreach ($clinical['encounters'] as $e)
                        @php($etz = $e->unit->timezone)
                        <li class="flex gap-3">
                            <div aria-hidden="true" class="relative hidden w-3 shrink-0 sm:block">
                                <span class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-line"></span>
                                <span class="absolute left-1/2 top-6 size-3 -translate-x-1/2 rounded-full ring-4 ring-bg {{ $e->status === 'draft' ? 'bg-warning' : 'bg-brand' }}"></span>
                            </div>
                            <a href="{{ route('encounters.show', $e) }}" class="group block min-w-0 flex-1">
                                <x-card :hover="true" class="p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-bold tabular-nums">{{ $e->started_at->timezone($etz)->format('d/m/Y H:i') }} <span class="font-semibold text-muted">· {{ $e->type?->name ?? 'Atendimento' }}</span></p>
                                        @if ($e->status === 'draft')
                                            <x-badge tone="warning" :dot="true">Rascunho</x-badge>
                                        @else
                                            <x-badge tone="success" :dot="true">Finalizado</x-badge>
                                        @endif
                                    </div>
                                    @if ($e->chief_complaint)
                                        <p class="mt-1.5 line-clamp-2 text-sm text-ink">{{ $e->chief_complaint }}</p>
                                    @endif
                                    @if ($e->diagnoses->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($e->diagnoses as $d)
                                                <x-badge :tone="['confirmed' => 'danger', 'suspected' => 'warning', 'ruled_out' => 'neutral'][$d->status]">{{ \App\Models\EncounterDiagnosis::STATUS[$d->status] }}: {{ $d->description }}</x-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                    <p class="mt-2 text-xs text-muted">
                                        {{ $e->status === 'finalized' ? 'Assinado por '.($e->signer?->name ?? '—') : 'Aberto por '.($e->author?->name ?? '—') }}
                                        @if ($e->weight_kg) · {{ rtrim(rtrim(number_format((float) $e->weight_kg, 3, ',', '.'), '0'), ',') }} kg @endif
                                        @if ($e->addenda_count) · {{ $e->addenda_count }} {{ $e->addenda_count === 1 ? 'adendo' : 'adendos' }} @endif
                                    </p>
                                </x-card>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <x-card>
                <h3 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="heart" :size="18" class="text-link" /> Peso</h3>
                @include('patients._weight-chart', ['weights' => $clinical['weights']])
            </x-card>

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
        </div>
    </div>
</section>
