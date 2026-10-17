{{-- Faixa de alertas clínicos ativos (alergias primeiro pela gravidade). Só renderizada para a equipe clínica. --}}
@if ($alerts->isNotEmpty())
    <section aria-label="Alertas clínicos" class="mb-6 animate-fade-up rounded-[1.25rem] border border-danger/25 bg-danger-bg p-4">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-danger text-white"><x-icon name="alert-circle" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-danger">Atenção: {{ $alerts->count() }} {{ $alerts->count() === 1 ? 'alerta clínico ativo' : 'alertas clínicos ativos' }}</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($alerts as $al)
                        <li class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1 text-sm font-semibold text-ink ring-1 ring-danger/30">
                            <span aria-hidden="true" class="size-2 rounded-full {{ $al->severity === 'high' ? 'bg-danger' : ($al->severity === 'medium' ? 'bg-warning' : 'bg-muted') }}"></span>
                            <span class="text-muted">{{ \App\Models\PatientAlert::KINDS[$al->kind] }}:</span> {{ $al->description }}
                            <span class="sr-only">(gravidade {{ mb_strtolower(\App\Models\PatientAlert::SEVERITY[$al->severity]) }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
@endif
