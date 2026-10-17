{{-- Exportação anonimizada de caso científico (dompdf). Sem nomes, responsáveis, documentos, microchip ou datas exatas. --}}
@php($primary = preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0e5e66')
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $case->title }}</title>
<style>
    @page { margin: 16mm 12mm 16mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #13232a; }
    .head { border-bottom: 2.5pt solid {{ $primary }}; padding-bottom: 3mm; margin-bottom: 4mm; }
    .clinic { font-size: 11pt; font-weight: bold; color: {{ $primary }}; }
    .muted { color: #4c5f66; }
    h1 { font-size: 14pt; margin: 0 0 2mm; }
    h2 { font-size: 10pt; margin: 5mm 0 1.5mm; color: {{ $primary }}; }
    p { margin: 0 0 2mm; line-height: 1.4; }
    table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    th { text-align: left; font-size: 6.5pt; text-transform: uppercase; color: #4c5f66; border-bottom: 1pt solid #13232a; padding: 1.2mm; }
    td { padding: 1.5mm 1.2mm; border-bottom: 0.5pt solid #d6dfe1; vertical-align: top; }
    .foot { margin-top: 5mm; font-size: 7pt; color: #4c5f66; }
</style>
</head>
<body>
<div class="head"><div class="clinic">{{ $tenant->trade_name }}</div><div class="muted">{{ \App\Models\ClinicalCase::KINDS[$case->kind] }}@if ($case->specialty) · {{ $case->specialty }}@endif · exportação anonimizada</div></div>
<h1>{{ $case->title }}</h1>
<h2>Pergunta clínica / objetivo</h2>
<p>{!! nl2br(e($question)) !!}</p>
@if ($case->criteria)
    <h2>Critérios de seleção</h2>
    <p>{{ implode(' · ', \App\Services\PatientSearch::describe(array_diff_key($case->criteria, ['q' => 1]))) ?: '—' }}. Somente atendimentos finalizados.</p>
@endif
@if (trim($summary) !== '')
    <h2>Resumo e discussão</h2>
    <p>{!! nl2br(e($summary)) !!}</p>
@endif
<h2>Casos ({{ count($rows) }})</h2>
@if ($rows === [])
    <p class="muted">Nenhum caso com consentimento do tutor registrado como autorizado.</p>
@else
    <table>
        <thead><tr><th>Caso</th><th>Espécie / raça</th><th>Sexo</th><th>Idade</th><th>Ref.</th><th>Peso</th><th>Diagnósticos</th><th>Desfecho</th><th>Observações</th></tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td><strong>{{ $r['caso'] }}</strong></td>
                    <td>{{ $r['especie'] }}@if ($r['raca'] !== '')<br><span class="muted">{{ $r['raca'] }}</span>@endif</td>
                    <td>{{ $r['sexo'] }}<br><span class="muted">{{ $r['castracao'] }}</span></td>
                    <td>{{ $r['idade_referencia'] ?: '—' }}</td>
                    <td>{{ $r['mes_referencia'] ?: '—' }}</td>
                    <td>{{ $r['peso_kg'] !== '' ? $r['peso_kg'].' kg' : '—' }}</td>
                    <td>{{ $r['diagnosticos'] ?: '—' }}</td>
                    <td>{{ $r['desfecho'] }}</td>
                    <td>{{ $r['observacoes'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
<p class="foot">
    Gerado em {{ $now->format('m/Y') }}. Pacientes identificados por pseudônimo; datas reduzidas a mês/ano; idade na data do atendimento de referência.
    @if ($excluded > 0) {{ $excluded }} {{ $excluded === 1 ? 'caso não incluído' : 'casos não incluídos' }} por falta de consentimento autorizado. @endif
    Diagnósticos conforme registrados pela equipe (suspeita ≠ confirmado). Antes de publicar, revise o texto e confirme a aprovação ética aplicável.
</p>
</body>
</html>
