{{-- Carteira de vacinação na ficha do paciente ($vaccination: card, history, vaccines). --}}
<section aria-labelledby="vacinas-titulo" class="mt-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="vacinas-titulo" class="flex items-center gap-2 text-xl font-bold tracking-tight"><span class="flex size-9 items-center justify-center rounded-xl bg-brand fv-gradient text-on-brand"><x-icon name="syringe" :size="18" /></span> Vacinação</h2>
        @if ($vaccination['history']->whereNull('voided_at')->isNotEmpty())
            <a href="{{ route('vaccinations.card', $patient) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint"><x-icon name="list" :size="18" /> Carteira em PDF</a>
        @endif
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            @if ($vaccination['card'] === [])
                <x-empty-state title="Nenhuma vacina registrada" icon="syringe" />
            @else
                <x-card class="p-1.5">
                    <ul class="divide-y divide-line">
                        @foreach ($vaccination['card'] as $row)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl px-3.5 py-3">
                                <span class="min-w-0 flex-1 basis-48">
                                    <span class="block font-semibold">{{ $row['vaccine']->name }}</span>
                                    <span class="block text-sm text-muted">Dose {{ $row['last']->dose_number }} em {{ $row['last']->applied_on->format('d/m/Y') }}{{ $row['last']->next_due_on ? ' · próxima '.$row['last']->next_due_on->format('d/m/Y') : '' }}</span>
                                </span>
                                @include('vaccinations._status', ['status' => $row['status'], 'days' => $row['days']])
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($vaccination['history']->isNotEmpty())
                <details class="rounded-[1.25rem] border border-line bg-surface p-4 shadow-card">
                    <summary class="min-h-8 cursor-pointer font-semibold">Histórico de aplicações ({{ $vaccination['history']->count() }})</summary>
                    <ul class="mt-3 flex flex-col gap-2 text-sm">
                        @foreach ($vaccination['history'] as $h)
                            <li class="rounded-xl bg-surface-2 p-3 {{ $h->voided_at ? 'opacity-70' : '' }}">
                                <p class="font-semibold {{ $h->voided_at ? 'line-through' : '' }}">{{ $h->applied_on->format('d/m/Y') }} · {{ $h->vaccine->name }} · dose {{ $h->dose_number }}</p>
                                <p class="text-xs text-muted">Lote {{ $h->lot }}{{ $h->manufacturer ? ' · '.$h->manufacturer : '' }}{{ $h->lot_expires_on ? ' · val. '.$h->lot_expires_on->format('m/Y') : '' }}{{ $h->site ? ' · '.$h->site : '' }} · {{ $h->appliedBy?->user?->name }}</p>
                                @if ($h->voided_at)
                                    <p class="mt-1 text-xs font-semibold text-danger">Anulada: {{ $h->void_reason }}</p>
                                @elseif ($fvCan('vaccines:write'))
                                    <details class="mt-1 text-xs">
                                        <summary class="inline-flex min-h-8 cursor-pointer items-center text-muted hover:text-ink">Anular aplicação<span class="sr-only"> de {{ $h->vaccine->name }} em {{ $h->applied_on->format('d/m/Y') }}</span></summary>
                                        <form method="POST" action="{{ route('vaccinations.void', $h) }}" class="mt-2 flex flex-wrap items-end gap-2" novalidate>
                                            @csrf
                                            <label for="vx-motivo-{{ $h->id }}" class="sr-only">Motivo da anulação</label>
                                            <input id="vx-motivo-{{ $h->id }}" name="reason" required maxlength="200" placeholder="Motivo (ex.: lote digitado errado)" class="min-h-10 flex-1 rounded-lg border border-line bg-surface px-2 text-base sm:text-sm">
                                            <x-button variant="secondary" class="min-h-10 text-xs">Anular</x-button>
                                        </form>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        @if ($fvCan('vaccines:write') && $patient->status === 'active')
            <x-card>
                <h3 class="mb-3 font-bold">Registrar aplicação</h3>
                @if ($vaccination['vaccines']->isEmpty())
                    <p class="text-sm text-muted">Nenhuma vacina do catálogo para esta espécie. @if ($fvCan('vaccines:configure'))<a class="font-semibold text-link underline underline-offset-4" href="{{ route('vaccines.create') }}">Cadastrar vacina</a>@endif</p>
                @else
                    <form method="POST" action="{{ route('vaccinations.store', $patient) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        @if ($errors->hasAny(['vaccine_id', 'lot', 'applied_on', 'lot_expires_on', 'next_due_on']))<x-alert>{{ $errors->first() }}</x-alert>@endif
                        <x-select name="vaccine_id" label="Vacina" :options="$vaccination['vaccines']->pluck('name', 'id')->all()" required />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="applied_on" label="Data da aplicação" type="date" :value="now('America/Sao_Paulo')->format('Y-m-d')" required />
                            <x-field name="dose_number" label="Dose nº" type="number" min="1" max="99" inputmode="numeric" hint="Vazio = automático" />
                            <x-field name="lot" label="Lote" required autocomplete="off" />
                            <x-field name="lot_expires_on" label="Validade do lote" type="date" />
                        </div>
                        <x-field name="manufacturer" label="Fabricante" autocomplete="off" />
                        <x-select name="site" label="Local de aplicação" :options="array_combine(\App\Models\Vaccination::SITES, \App\Models\Vaccination::SITES)" placeholder="—" />
                        <x-field name="next_due_on" label="Próxima dose" type="date" hint="Vazio = calculada pelo protocolo da vacina." />
                        <x-button><x-icon name="syringe" :size="18" /> Registrar vacina</x-button>
                    </form>
                @endif
            </x-card>
        @endif
    </div>
</section>
