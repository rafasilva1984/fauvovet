@extends('layouts.app')
@section('title', 'Dados de demonstração')

@section('content')
    <x-page-header title="Dados de demonstração" icon="sparkles" eyebrow="Configurações"
        description="Pacientes, atendimentos, vacinas, agenda e casos científicos fictícios para conhecer o sistema. Tudo fica nesta clínica e pode ser removido com um clique." />

    @error('demo')<x-alert class="mb-6">{{ $message }}</x-alert>@enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold">Situação</h2>
            @if ($counts['patients'] > 0)
                <x-alert tone="info">
                    Demonstração carregada: {{ $counts['patients'] }} pacientes, {{ $counts['guardians'] }} responsáveis,
                    {{ $counts['encounters'] }} atendimentos e {{ $counts['cases'] }} casos científicos.
                </x-alert>
                <p class="text-sm text-muted">Os responsáveis aparecem com “(demo)” no nome. Sugestões para ver as novidades:</p>
                <ul class="flex flex-col gap-2 text-sm">
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('patients.index', ['diagnostico' => 'dermatite atópica', 'situacao' => 'confirmed', 'especie' => 'canine']) }}">Busca: dermatite atópica confirmada em cães</a></li>
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('patients.index', ['diagnostico' => 'renal', 'especie' => 'feline', 'idade_min' => 10]) }}">Busca: doença renal em gatos com 10 anos ou mais</a></li>
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('patients.index', ['ano' => now('America/Sao_Paulo')->year - 2]) }}">Busca: atendidos em {{ now('America/Sao_Paulo')->year - 2 }}</a></li>
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('vaccinations.index') }}">Vacinas atrasadas e a vencer</a></li>
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('cases.index') }}">Casos científicos (série aberta, série encerrada e relato)</a></li>
                    <li><a class="font-semibold text-link underline underline-offset-4" href="{{ route('agenda.index') }}">Agenda de hoje</a></li>
                </ul>
            @else
                <p class="text-sm">Nenhum dado de demonstração nesta clínica.</p>
                <p class="text-sm text-muted">
                    Serão criados 10 responsáveis e 22 pacientes (cães, gatos, aves, jabuti, coelho, hamster e furão), 32 atendimentos
                    finalizados em 3 anos com diagnósticos suspeitos, confirmados e descartados, pesos, alertas, 15 vacinas
                    (atrasadas, a vencer e em dia), 10 agendamentos nesta semana e 3 casos científicos.
                </p>
                <form method="POST" action="{{ route('settings.demo-data.load') }}">
                    @csrf
                    <x-button><x-icon name="sparkles" /> Carregar demonstração</x-button>
                </form>
            @endif
        </x-card>

        <x-card class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold">Remover</h2>
            <p class="text-sm text-muted">
                Apaga os pacientes e responsáveis de demonstração e <strong class="text-ink">tudo o que for registrado neles</strong>
                (atendimentos, vacinas, documentos e anexos), além dos casos de demonstração. Os demais cadastros da clínica não são tocados.
            </p>
            <form method="POST" action="{{ route('settings.demo-data.remove') }}" class="flex flex-col gap-3">
                @csrf
                <label class="flex min-h-11 items-center gap-3 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="size-5 rounded border-line" @disabled($counts['patients'] === 0 && $counts['cases'] === 0)>
                    Entendo que os dados de demonstração serão apagados
                </label>
                @error('confirm')<p class="text-xs font-medium text-danger">{{ $message }}</p>@enderror
                <div><x-button variant="secondary" :disabled="$counts['patients'] === 0 && $counts['cases'] === 0">Remover demonstração</x-button></div>
            </form>
        </x-card>
    </div>
@endsection
