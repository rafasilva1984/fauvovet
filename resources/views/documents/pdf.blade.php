{{-- PDF do documento (dompdf). Todo texto vem ESCAPADO ({{ }}): o corpo é texto puro, nunca HTML. Sem recursos remotos. --}}
@php
    $v = $values;
    $paragraphs = preg_split("/\n{2,}/", trim(str_replace("\r", '', $body))) ?: [];
    $signedByVet = in_array($category, \App\Models\DocumentTemplate::SIGNED_BY_VET, true);
    $primary = preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#0e5e66';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 26mm 18mm 22mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; color: #13232a; line-height: 1.55; }
    header { position: fixed; top: -19mm; left: 0; right: 0; height: 15mm; border-bottom: 2.5pt solid {{ $primary }}; }
    header .name { font-size: 13pt; font-weight: bold; color: {{ $primary }}; }
    header .meta { font-size: 8pt; color: #4c5f66; }
    footer { position: fixed; bottom: -15mm; left: 0; right: 0; height: 11mm; border-top: 0.6pt solid #c9d4d6; font-size: 7.5pt; color: #4c5f66; padding-top: 2mm; }
    h1 { font-size: 15pt; text-align: center; margin: 2mm 0 6mm; letter-spacing: 0.5pt; }
    table.id { width: 100%; border-collapse: collapse; margin-bottom: 7mm; font-size: 9pt; }
    table.id td { padding: 2mm 3mm; border: 0.6pt solid #d6dfe1; vertical-align: top; }
    table.id .lbl { color: #4c5f66; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt; display: block; }
    p { margin: 0 0 3.5mm; text-align: justify; }
    .sign { margin-top: 16mm; width: 100%; }
    .sign td { width: 50%; text-align: center; vertical-align: bottom; font-size: 9pt; padding: 0 4mm; }
    .line { border-top: 0.8pt solid #13232a; padding-top: 1.5mm; }
    .muted { color: #4c5f66; font-size: 8pt; }
    .place { text-align: right; margin-top: 8mm; }
</style>
</head>
<body>
<header>
    <div class="name">{{ $tenant->trade_name }}</div>
    <div class="meta">
        @if ($v['clinica.documento']){{ strlen((string) $tenant->document_number) === 14 ? 'CNPJ' : 'CPF' }} {{ $v['clinica.documento'] }} · @endif{{ $v['unidade.nome'] }}@if ($v['unidade.endereco']) · {{ $v['unidade.endereco'] }}@endif @if ($v['unidade.cidade']) · {{ $v['unidade.cidade'] }}@endif
    </div>
</header>
<footer>
    Emitido eletronicamente pelo FauvoVet em {{ $issuedAt->format('d/m/Y \à\s H:i') }} · Código de verificação: <strong>{{ $code }}</strong> · Documento {{ $id }}
</footer>

<h1>{{ mb_strtoupper($title) }}</h1>

<table class="id">
    <tr>
        <td style="width: 50%"><span class="lbl">Paciente</span>{{ $patient->name }} — {{ $v['paciente.especie'] }}<br>{{ $v['paciente.raca'] }} · {{ $v['paciente.sexo'] }} · {{ $v['paciente.idade'] }} · {{ $v['paciente.peso_atual'] }}</td>
        <td><span class="lbl">Responsável</span>{{ $v['responsavel.nome'] ?: 'não informado' }}@if ($v['responsavel.documento'])<br>Doc. {{ $v['responsavel.documento'] }}@endif @if ($v['responsavel.telefone']) · {{ $v['responsavel.telefone'] }}@endif @if ($v['responsavel.endereco'])<br>{{ $v['responsavel.endereco'] }}@endif</td>
    </tr>
</table>

@foreach ($paragraphs as $p)
    <p>{!! nl2br(e($p)) !!}</p>
@endforeach

<p class="place">{{ $v['unidade.cidade'] ? explode('/', $v['unidade.cidade'])[0].', ' : '' }}{{ $issuedAt->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y') }}.</p>

<table class="sign">
    <tr>
        @if ($category === 'consent')
            <td><div class="line">{{ $v['responsavel.nome'] ?: 'Responsável pelo animal' }}<br><span class="muted">Responsável pelo animal</span></div></td>
        @endif
        <td @if ($category !== 'consent') colspan="2" @endif>
            <div class="line" @if ($category !== 'consent') style="width: 60%; margin: 0 auto;" @endif>
                {{ $issuer->name }}<br>
                <span class="muted">{{ $signedByVet || $issuer->crmv ? 'Médico(a)-veterinário(a)'.($issuer->crmv ? ' · '.$issuer->crmv : '') : 'Equipe '.$tenant->trade_name }}</span>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
