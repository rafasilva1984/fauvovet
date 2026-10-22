@extends('layouts.app')
@section('title', 'Nova receita — '.$patient->name)

@php
    use App\Models\Medication;
    $options = fn (array $list) => array_combine($list, $list);
    $medOptions = $medications->mapWithKeys(fn ($m) => [$m->id => $m->label().($m->controlled ? ' (controle especial)' : '')])->all();
    $byRow = $plan ? collect($plan['items'])->keyBy('row') : collect();
@endphp

@section('content')
    <x-page-header icon="pill" eyebrow="Receita" :title="'Receita para '.$patient->name"
        :description="($encounter ? 'Vinculada ao atendimento de '.$encounter->started_at->timezone('America/Sao_Paulo')->format('d/m/Y') : 'Sem atendimento vinculado').'. Sai em PDF sobre o receituário da clínica.'">
        <a href="{{ route('patients.show', $patient) }}#documentos" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card transition hover:border-brand/40 hover:bg-tint"><x-icon name="paw" :size="18" /> Ficha do paciente</a>
    </x-page-header>

    @if ($needsCrmv)
        <x-alert tone="warning" class="mb-6">Seu CRMV não está cadastrado. Receitas exigem o CRMV: <a class="font-semibold underline underline-offset-4" href="{{ route('profile.edit') }}">abrir Meu perfil</a>.</x-alert>
    @endif
    @if ($allergies->isNotEmpty())
        <div class="mb-6 flex items-start gap-3 rounded-[1.25rem] border border-danger/30 bg-danger-bg p-4 text-sm text-danger" role="note">
            <x-icon name="alert-circle" :size="20" class="mt-0.5 shrink-0" />
            <p><strong>Alergias registradas:</strong> {{ $allergies->pluck('description')->join('; ') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('prescriptions.store', $patient) }}" class="flex min-w-0 flex-col gap-4" novalidate>
            @csrf
            @if ($encounter)<input type="hidden" name="encounter_id" value="{{ $encounter->id }}">@endif
            @if ($errors->any() && ! $errors->has('confirm_warnings'))<x-alert>{{ $errors->first('rows') ?: $errors->first() }}</x-alert>@endif

            <x-card>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field name="weight_kg" label="Peso usado no cálculo (kg)" inputmode="decimal" :value="$weight !== null ? Medication::num($weight, 3) : null" placeholder="0,000" hint="Vem do registro mais recente. Ajuste se pesou agora." />
                    <x-select name="background" id="receita-fundo" label="Fundo" :options="\App\Models\DocumentBackground::options($backgrounds)" value="auto" />
                </div>
            </x-card>

            @if ($medications->isEmpty())
                <x-alert tone="info">O catálogo de medicamentos da clínica está vazio: digite os medicamentos (sem cálculo pelo peso)@if ($fvCan('medications:configure')) ou <a class="font-semibold underline underline-offset-4" href="{{ route('medications.create') }}">cadastre no catálogo</a>@endif.</x-alert>
            @endif

            @foreach ($rows as $i => $row)
                @php($item = $byRow->get($i))
                <fieldset class="rounded-[1.25rem] border border-line bg-surface p-4 shadow-card">
                    <legend class="px-1 text-sm font-bold">Medicamento {{ $i + 1 }}</legend>
                    <input type="hidden" name="rows[{{ $i }}][dose_auto]" value="{{ $row['dose_auto'] ?? '' }}">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-select :name="'rows['.$i.'][medication_id]'" :id="'rx-'.$i.'-m'" label="Do catálogo" :options="$medOptions" :value="$row['medication_id'] ?? ''" placeholder="— digitar fora do catálogo —" />
                        <x-field :name="'rows['.$i.'][name]'" :id="'rx-'.$i.'-n'" label="Ou digite o medicamento" :value="$row['name'] ?? ''" hint="Fora do catálogo não há cálculo pelo peso." />
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-6">
                        <x-field :name="'rows['.$i.'][presentation]'" :id="'rx-'.$i.'-p'" label="Apresentação" :value="$row['presentation'] ?? ''" class="col-span-2 sm:col-span-2" placeholder="Ex.: comprimido 250 mg" />
                        <x-field :name="'rows['.$i.'][dose_mg_kg]'" :id="'rx-'.$i.'-k'" label="Dose (mg/kg)" inputmode="decimal" :value="$row['dose_mg_kg'] ?? ''" class="sm:col-span-1" />
                        <x-field :name="'rows['.$i.'][dose_text]'" :id="'rx-'.$i.'-d'" label="Dose por administração" :value="$row['dose_text'] ?? ''" class="sm:col-span-3" hint="Vazio = calculada pelo peso." />
                        <x-select :name="'rows['.$i.'][route]'" :id="'rx-'.$i.'-r'" label="Via" :options="$options(Medication::ROUTES)" :value="$row['route'] ?? ''" placeholder="—" class="sm:col-span-2" />
                        <x-select :name="'rows['.$i.'][frequency]'" :id="'rx-'.$i.'-f'" label="Frequência" :options="$options(Medication::FREQUENCIES)" :value="$row['frequency'] ?? ''" placeholder="—" class="sm:col-span-2" />
                        <x-field :name="'rows['.$i.'][duration_days]'" :id="'rx-'.$i.'-t'" label="Por (dias)" type="number" inputmode="numeric" min="1" max="365" :value="$row['duration_days'] ?? ''" class="sm:col-span-1" />
                        <label class="flex min-h-11 items-center gap-2 self-end text-sm sm:col-span-1">
                            <input type="hidden" name="rows[{{ $i }}][continuous]" value="0">
                            <input type="checkbox" name="rows[{{ $i }}][continuous]" value="1" class="size-4" @checked(filter_var($row['continuous'] ?? false, FILTER_VALIDATE_BOOL))> Uso contínuo
                        </label>
                        <x-field :name="'rows['.$i.'][instructions]'" :id="'rx-'.$i.'-i'" label="Instruções" :value="$row['instructions'] ?? ''" class="col-span-2 sm:col-span-6" placeholder="Ex.: dar junto com a refeição" />
                    </div>
                    @if ($item && $item['calc'])
                        @php($c = $item['calc'])
                        <p class="mt-3 rounded-xl bg-tint px-3 py-2 text-sm" data-testid="calc-{{ $i }}">
                            <strong>Cálculo:</strong> {{ Medication::num((float) $item['calc_dose_mg_kg']) }} mg/kg × {{ Medication::num((float) $weight, 3) }} kg = {{ \App\Clinical\DoseCalculator::mg($c['target_mg']) }}
                            @if ($c['quantity'] !== null) → <strong>{{ $c['text'] ?: 'menos que a menor fração' }}</strong> · dose efetiva {{ Medication::num($c['effective_mg_kg'], 2) }} mg/kg @endif
                            @unless ($item['from_calc'])<span class="block text-muted">A dose escrita acima prevalece sobre o cálculo.</span>@endunless
                        </p>
                    @endif
                </fieldset>
            @endforeach

            <x-card>
                <x-textarea name="guidance" label="Orientações gerais" :value="old('guidance')" rows="3" hint="Cuidados, horários, retorno. Sai ao fim da receita." />
            </x-card>

            @if ($plan && $plan['warnings'] !== [])
                <div class="rounded-[1.25rem] border border-warning/30 bg-warning-bg p-4 text-sm text-warning" role="alert" data-testid="rx-warnings">
                    <p class="flex items-center gap-2 font-bold"><x-icon name="alert-circle" :size="18" /> Confira antes de emitir</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($plan['warnings'] as $w)<li>{{ $w }}</li>@endforeach
                    </ul>
                    <label class="mt-3 flex min-h-11 items-start gap-3 text-ink">
                        <input type="checkbox" name="confirm_warnings" value="1" class="mt-0.5 size-4">
                        <span>Revisei os avisos e confirmo a prescrição como responsável técnico.</span>
                    </label>
                    @error('confirm_warnings')<p class="mt-1 font-semibold text-danger">{{ $message }}</p>@enderror
                </div>
            @endif

            @if ($body)
                <x-card>
                    <h2 class="mb-2 font-bold">Prévia do texto da receita</h2>
                    <div class="whitespace-pre-line rounded-xl bg-surface-2 p-4 text-sm leading-relaxed" data-testid="rx-preview">{{ $body }}</div>
                </x-card>
            @endif

            <div class="flex flex-col gap-3 rounded-2xl bg-tint p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="text-muted">“Calcular e revisar” não grava nada e acrescenta uma linha. Depois de emitida, a receita <strong class="text-ink">não pode ser editada</strong>; anule e emita outra.</p>
                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                    <x-button variant="secondary" name="action" value="preview">Calcular e revisar</x-button>
                    <x-button name="action" value="issue"><x-icon name="check-circle" :size="18" /> Emitir receita</x-button>
                </div>
            </div>
        </form>

        <aside class="flex flex-col gap-4">
            <x-card>
                <h2 class="mb-2 flex items-center gap-2 font-bold"><x-icon name="pill" :size="18" class="text-link" /> Em uso</h2>
                @include('prescriptions._current', ['current' => $current])
            </x-card>
            <x-alert tone="info">O sistema faz a conta e mostra avisos (alergia registrada, faixa da clínica, espécie). Quem decide e assina é o médico-veterinário.</x-alert>
        </aside>
    </div>
@endsection
