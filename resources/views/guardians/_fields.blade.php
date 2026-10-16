{{-- Campos do responsável. $p = prefixo dos nomes (ex.: "g_" no cadastro rápido dentro do paciente). --}}
@php($p = $p ?? '')
@php($full = $full ?? true)
<div class="grid gap-4 sm:grid-cols-2">
    <x-field :name="$p.'name'" label="Nome completo" autocomplete="off" required :value="$guardian?->name" class="sm:col-span-2" />
    <x-field :name="$p.'phone'" label="Celular / telefone" type="tel" inputmode="tel" autocomplete="off" hint="Com DDD. Informe telefone ou e-mail." :value="\App\Models\Guardian::formatPhone($guardian?->phone)" />
    <x-field :name="$p.'email'" label="E-mail" type="email" inputmode="email" autocomplete="off" :value="$guardian?->email" />
    <x-select :name="$p.'person_type'" label="Tipo" :options="['PF' => 'Pessoa física (CPF)', 'PJ' => 'Pessoa jurídica (CNPJ)']" :value="$guardian?->person_type ?? 'PF'" />
    <x-field :name="$p.'document'" label="CPF ou CNPJ (opcional)" inputmode="numeric" autocomplete="off" :value="$guardian?->document_number" />
    @if ($full)
        <x-field :name="$p.'phone_alt'" label="Outro telefone" type="tel" inputmode="tel" autocomplete="off" :value="\App\Models\Guardian::formatPhone($guardian?->phone_alt)" />
        <x-field :name="$p.'postal_code'" label="CEP" inputmode="numeric" autocomplete="off" :value="$guardian?->postal_code" />
        <x-field :name="$p.'address_line'" label="Endereço" autocomplete="off" :value="$guardian?->address_line" class="sm:col-span-2" />
        <div class="grid grid-cols-[minmax(0,1fr)_5.5rem] gap-3 sm:col-span-2">
            <x-field :name="$p.'city'" label="Cidade" autocomplete="off" :value="$guardian?->city" />
            <x-field :name="$p.'state'" label="UF" maxlength="2" autocomplete="off" :value="$guardian?->state" />
        </div>
        <x-textarea :name="$p.'notes'" label="Observações (visíveis à equipe)" :value="$guardian?->notes" hint="Ex.: melhor horário para contato. Não registre informações clínicas aqui." class="sm:col-span-2" />
    @endif
</div>
