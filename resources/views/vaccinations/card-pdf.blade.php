{{-- Carteira de vacinação (dompdf). Tudo escapado; sem recursos remotos. --}}
@php
    $v = $values;
    $primary = preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0e5e66';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Carteira de vacinação — {{ $patient->name }}</title>
<style>
    @page { margin: 18mm 14mm 18mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #13232a; }
    .head { border-bottom: 2.5pt solid {{ $primary }}; padding-bottom: 3mm; margin-bottom: 5mm; }
    .clinic { font-size: 13pt; font-weight: bold; color: {{ $primary }}; }
    h1 { font-size: 14pt; margin: 0 0 4mm; }
    table { width: 100%; border-collapse: collapse; }
    .id td { padding: 1.5mm 2mm; border: 0.6pt solid #d6dfe1; font-size: 9pt; }
    .lbl { color: #4c5f66; font-size: 7pt; text-transform: uppercase; display: block; }
    .doses { margin-top: 5mm; }
    .doses th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #4c5f66; border-bottom: 1pt solid #13232a; padding: 1.5mm; }
    .doses td { padding: 2mm 1.5mm; border-bottom: 0.5pt solid #d6dfe1; vertical-align: top; }
    .foot { margin-top: 6mm; font-size: 7.5pt; color: #4c5f66; }
</style>
</head>
<body>
<div class="head"><div class="clinic">{{ $tenant->trade_name }}</div><div style="font-size:8pt;color:#4c5f66">{{ $v['unidade.nome'] }}{{ $v['unidade.cidade'] ? ' · '.$v['unidade.cidade'] : '' }}</div></div>
<h1>Carteira de vacinação</h1>
<table class="id"><tr>
    <td style="width:50%"><span class="lbl">Paciente</span>{{ $patient->name }} — {{ $v['paciente.especie'] }}<br>{{ $v['paciente.raca'] }} · {{ $v['paciente.sexo'] }} · {{ $v['paciente.idade'] }} · microchip {{ $v['paciente.microchip'] }}</td>
    <td><span class="lbl">Responsável</span>{{ $v['responsavel.nome'] ?: 'não informado' }}{{ $v['responsavel.telefone'] ? ' · '.$v['responsavel.telefone'] : '' }}</td>
</tr></table>
<table class="doses">
    <tr><th>Data</th><th>Vacina</th><th>Dose</th><th>Lote / fabricante / validade</th><th>Profissional</th><th>Próxima</th></tr>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r->applied_on->format('d/m/Y') }}</td>
            <td>{{ $r->vaccine->name }}</td>
            <td>{{ $r->dose_number }}ª</td>
            <td>{{ $r->lot }}{{ $r->manufacturer ? ' · '.$r->manufacturer : '' }}{{ $r->lot_expires_on ? ' · val. '.$r->lot_expires_on->format('m/Y') : '' }}</td>
            <td>{{ $r->appliedBy?->user?->name }}{{ $r->appliedBy?->user?->crmv ? ' · '.$r->appliedBy->user->crmv : '' }}</td>
            <td>{{ $r->next_due_on?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    @endforeach
</table>
<p class="foot">Emitida pelo FauvoVet em {{ $now->format('d/m/Y \à\s H:i') }}. Registro conforme o prontuário da clínica; aplicações anuladas não aparecem.</p>
</body>
</html>
