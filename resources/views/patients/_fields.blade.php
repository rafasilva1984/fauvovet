{{-- Campos do paciente (sem dados clínicos: alergias, peso e prontuário ficam no módulo clínico). --}}
<div class="grid gap-4 sm:grid-cols-2">
    <x-field name="name" label="Nome do paciente" autocomplete="off" required :value="$patient->name" />
    <x-select name="species" label="Espécie" required :options="\App\Support\Species::LABELS" :value="$patient->species" />
    <x-field name="species_detail" label="Qual espécie / tipo" autocomplete="off" :value="$patient->species_detail" hint="Para aves, répteis e exóticos: ex.: Calopsita, Jabuti-piranga, Hamster sírio." />
    <x-field name="breed" label="Raça" autocomplete="off" :value="$patient->breed" hint="Ex.: SRD, Shih Tzu, Persa." />
    <x-select name="sex" label="Sexo" :options="\App\Models\Patient::SEX" :value="$patient->sex" />
    <x-select name="neutered" label="Castração" :options="\App\Models\Patient::NEUTERED" :value="$patient->neutered" />
    <fieldset class="flex flex-col gap-3 rounded-xl border border-line p-4 sm:col-span-2" x-data="birthAge">
        <legend class="px-1 text-sm font-medium text-ink">Nascimento</legend>
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <x-field name="birth_date" label="Data de nascimento" type="date" :value="$patient->birth_date?->format('Y-m-d')" x-ref="birth" x-on:input="fromBirth" x-on:change="fromBirth" max="{{ now()->format('Y-m-d') }}" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="age_years" label="ou idade: anos" type="number" min="0" max="150" inputmode="numeric" x-ref="years" x-on:input="fromAge" />
                <x-field name="age_months" label="meses" type="number" min="0" max="11" inputmode="numeric" x-ref="months" x-on:input="fromAge" />
            </div>
        </div>
        <label class="flex min-h-11 items-center gap-3 text-sm">
            <input type="hidden" name="birth_date_estimated" value="0">
            <input type="checkbox" name="birth_date_estimated" value="1" class="size-4" @checked(old('birth_date_estimated', $patient->birth_date_estimated))>
            <span>A data é estimada (mostrada com “~”). Ao informar a data, a idade é calculada; ao informar só a idade, a data fica estimada.</span>
        </label>
    </fieldset>
    <x-field name="coat" label="Pelagem / cor" autocomplete="off" :value="$patient->coat" />
    <x-field name="microchip" label="Microchip / identificação" autocomplete="off" :value="$patient->microchip" />
    <x-textarea name="marks" label="Sinais particulares" :value="$patient->marks" hint="Ex.: mancha branca na pata dianteira esquerda." class="sm:col-span-2" />
</div>
