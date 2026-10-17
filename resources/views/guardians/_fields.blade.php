{{-- Campos do responsável. $p = prefixo dos nomes (ex.: "g_" no cadastro rápido dentro do paciente). --}}
@php($p = $p ?? '')
@php($full = $full ?? true)
<div class="grid gap-4 sm:grid-cols-2">
    <x-field :name="$p.'name'" label="Nome completo" autocomplete="off" required :value="$guardian?->name" class="sm:col-span-2" />
    <x-field :name="$p.'phone'" label="Celular / telefone" type="tel" inputmode="tel" autocomplete="off" hint="Com DDD. Informe telefone ou e-mail." :value="\App\Models\Guardian::formatPhone($guardian?->phone)" />
    <x-field :name="$p.'email'" label="E-mail" type="email" inputmode="email" autocomplete="off" :value="$guardian?->email" />
    <x-select :name="$p.'person_type'" label="Tipo" :options="['PF' => 'Pessoa física (CPF)', 'PJ' => 'Pessoa jurídica (CNPJ)']" :value="$guardian?->person_type ?? 'PF'" />
    <x-field :name="$p.'document'" label="CPF ou CNPJ" inputmode="numeric" autocomplete="off" :value="$guardian?->document_number" />
    @if ($full)
        {{-- Endereço: o CEP preenche rua, bairro, cidade e UF (consulta pelo servidor; dá para editar). --}}
        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-6" x-data="cepLookup" data-url="{{ route('cep', ['cep' => '00000000']) }}">
            <div class="sm:col-span-2">
                <x-field :name="$p.'postal_code'" label="CEP" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="00000-000" :value="$guardian?->postal_code" x-ref="cep" x-on:input="lookup" x-on:blur="lookup" />
                <p id="cep-status" class="mt-1 min-h-4 text-xs" x-bind:class="statusClass" x-text="status" aria-live="polite"></p>
            </div>
            <x-field :name="$p.'address_line'" label="Rua / logradouro" autocomplete="off" :value="$guardian?->address_line" class="sm:col-span-4" x-ref="street" />
            <x-field :name="$p.'address_number'" label="Número" autocomplete="off" :value="$guardian?->address_number" class="sm:col-span-2" x-ref="number" />
            <x-field :name="$p.'address_complement'" label="Complemento" autocomplete="off" :value="$guardian?->address_complement" class="sm:col-span-4" />
            <x-field :name="$p.'district'" label="Bairro" autocomplete="off" :value="$guardian?->district" class="sm:col-span-2" x-ref="district" />
            <x-field :name="$p.'city'" label="Cidade" autocomplete="off" :value="$guardian?->city" class="sm:col-span-2" x-ref="city" />
            <x-select :name="$p.'state'" label="UF" :options="\App\Support\BrazilianStates::options()" :value="$guardian?->state" placeholder="Selecione" class="sm:col-span-2" x-ref="state" />
        </div>
        <x-field :name="$p.'phone_alt'" label="Outro telefone" type="tel" inputmode="tel" autocomplete="off" :value="\App\Models\Guardian::formatPhone($guardian?->phone_alt)" />
        <x-textarea :name="$p.'notes'" label="Observações (visíveis à equipe)" :value="$guardian?->notes" hint="Ex.: melhor horário para contato. Não registre informações clínicas aqui." class="sm:col-span-2" />
    @endif
</div>
