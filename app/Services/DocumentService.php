<?php

namespace App\Services;

use App\Documents\Placeholders;
use App\Models\DocumentTemplate;
use App\Models\Encounter;
use App\Models\Guardian;
use App\Models\IssuedDocument;
use App\Models\Patient;
use App\Models\PatientWeight;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Species;
use App\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Documentos clínicos: modelos da clínica (whitelist de variáveis), emissão em PDF (dompdf, sem recursos remotos),
 * arquivo em disco PRIVADO (storage/app/private, fora do public_html), hash SHA-256 e código de verificação.
 */
final class DocumentService
{
    /** Modelos iniciais (editáveis pela clínica). [nome, categoria, título, texto] */
    public const DEFAULTS = [
        ['Receita simples', 'prescription', 'Receituário', "Uso veterinário — {{paciente.nome}} ({{paciente.especie}}, {{paciente.peso_atual}})\n\n1. [Medicamento, concentração e apresentação]\n   [Dose] — via [via] — a cada [intervalo] — por [duração].\n\n2. [Medicamento, concentração e apresentação]\n   [Dose] — via [via] — a cada [intervalo] — por [duração].\n\nOrientações: [cuidados, horários, retorno]."],
        ['Atestado de saúde', 'certificate', 'Atestado de saúde', "Atesto, para os devidos fins, que examinei em {{data.hoje}} o animal {{paciente.nome}}, espécie {{paciente.especie}}, raça {{paciente.raca}}, sexo {{paciente.sexo}}, idade {{paciente.idade}}, microchip {{paciente.microchip}}, de responsabilidade de {{responsavel.nome}}, e que no momento do exame não apresenta sinais clínicos de doenças infectocontagiosas ou parasitárias, encontrando-se em condições de [finalidade: viagem, hospedagem, banho e tosa…].\n\nEste atestado tem validade de [prazo] dias."],
        ['Orientações pós-consulta', 'guidance', 'Orientações ao tutor', "Orientações para o cuidado de {{paciente.nome}}:\n\n{{atendimento.orientacoes}}\n\nEm caso de piora, falta de apetite, vômitos ou apatia, entre em contato com {{clinica.nome}}."],
        ['Encaminhamento', 'referral', 'Encaminhamento', "Encaminho o paciente {{paciente.nome}} ({{paciente.especie}}, {{paciente.raca}}, {{paciente.idade}}), de responsabilidade de {{responsavel.nome}}, para avaliação em [especialidade/serviço].\n\nResumo clínico: {{atendimento.queixa}}\n\nDiagnósticos confirmados: {{atendimento.diagnosticos}}\n\nConduta até o momento: {{atendimento.conduta}}\n\nColoco-me à disposição para informações adicionais."],
        ['Termo de consentimento', 'consent', 'Termo de consentimento livre e esclarecido', "Eu, {{responsavel.nome}}, documento {{responsavel.documento}}, residente em {{responsavel.endereco}}, responsável pelo animal {{paciente.nome}} ({{paciente.especie}}, {{paciente.raca}}), autorizo a realização de [procedimento/anestesia] pela equipe de {{clinica.nome}}.\n\nDeclaro ter sido informado(a) sobre o procedimento, seus riscos, benefícios e alternativas, e que tive a oportunidade de esclarecer minhas dúvidas.\n\nDeclaro ainda estar ciente de que, em situações de emergência durante o procedimento, a equipe poderá adotar as medidas necessárias para preservar a vida do animal."],
    ];

    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function ensureDefaultTemplates(): void
    {
        if (DocumentTemplate::query()->exists()) {
            return;
        }
        DB::transaction(function () {
            foreach (self::DEFAULTS as [$name, $category, $title, $body]) {
                DocumentTemplate::create(['name' => $name, 'category' => $category, 'title' => $title, 'body' => $body, 'version' => 1, 'active' => true]);
            }
        });
    }

    /** Pode emitir documentos desta categoria? (receita/atestado/encaminhamento: só veterinário) */
    public function canIssue(string $category): bool
    {
        return in_array($category, DocumentTemplate::SIGNED_BY_VET, true)
            ? $this->context->can('clinical:sign')
            : $this->context->can('clinical:write');
    }

    /**
     * Valores das variáveis para este paciente/atendimento/profissional (texto puro).
     *
     * @return array<string, string>
     */
    public function values(Patient $patient, ?Encounter $encounter, User $issuer): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $unit = $this->unitFor($encounter);
        $guardian = $patient->guardians()->orderByRaw("FIELD(guardian_patient.relationship, 'primary', 'co_guardian', 'authorized')")->first();
        $weight = PatientWeight::query()->where('patient_id', $patient->id)->orderByDesc('measured_at')->value('weight_kg') ?? $encounter?->weight_kg;
        $fmtKg = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',').' kg';
        $doc = fn (?string $d) => match (strlen((string) $d)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', (string) $d),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', (string) $d),
            default => (string) $d,
        };
        $tz = $unit->timezone ?? 'America/Sao_Paulo';
        $today = now($tz);

        return [
            'clinica.nome' => (string) $tenant->trade_name,
            'clinica.documento' => (string) $doc($tenant->document_number),
            'unidade.nome' => (string) $unit?->name,
            'unidade.endereco' => (string) $unit?->address_line,
            'unidade.cidade' => collect([$unit?->city, $unit?->state])->filter()->join('/'),
            'profissional.nome' => $issuer->name,
            'profissional.crmv' => (string) $issuer->crmv,
            'responsavel.nome' => (string) $guardian?->name,
            'responsavel.documento' => (string) $doc($guardian?->document_number),
            'responsavel.telefone' => Guardian::formatPhone($guardian?->phone),
            'responsavel.endereco' => $guardian ? collect([collect([$guardian->address_line, $guardian->address_number])->filter()->join(', '), $guardian->district,
                collect([$guardian->city, $guardian->state])->filter()->join('/')])->filter()->join(' — ') : '',
            'paciente.nome' => $patient->name,
            'paciente.especie' => Species::label($patient->species).($patient->species_detail ? ' — '.$patient->species_detail : ''),
            'paciente.raca' => $patient->breed ?: 'não informada',
            'paciente.sexo' => mb_strtolower(Patient::SEX[$patient->sex]),
            'paciente.idade' => $patient->ageLabel() ?? 'não informada',
            'paciente.peso_atual' => $weight !== null ? $fmtKg($weight) : 'peso não registrado',
            'paciente.microchip' => $patient->microchip ?: 'não informado',
            'atendimento.data' => $encounter ? $encounter->started_at->timezone($tz)->format('d/m/Y') : '',
            'atendimento.queixa' => (string) $encounter?->chief_complaint,
            'atendimento.conduta' => (string) $encounter?->plan,
            'atendimento.orientacoes' => (string) $encounter?->guidance,
            'atendimento.diagnosticos' => $encounter ? $encounter->diagnoses()->where('status', 'confirmed')->pluck('description')->join('; ') : '',
            'data.hoje' => $today->format('d/m/Y'),
            'data.extenso' => $today->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y'),
        ];
    }

    /** Emite: valida permissão/CRMV, gera o PDF, grava em disco privado com hash e registra auditoria. */
    public function issue(Patient $patient, ?Encounter $encounter, ?DocumentTemplate $template, string $category, string $title, string $body, User $issuer): IssuedDocument
    {
        if (! $this->canIssue($category)) {
            abort(403);
        }
        if (in_array($category, DocumentTemplate::SIGNED_BY_VET, true) && trim((string) $issuer->crmv) === '') {
            throw ValidationException::withMessages(['body' => 'Cadastre seu CRMV em “Meu perfil” antes de emitir receitas, atestados ou encaminhamentos.']);
        }
        if (($bad = Placeholders::unknown($body)) !== []) {
            throw ValidationException::withMessages(['body' => 'Variáveis não reconhecidas: {{'.implode('}}, {{', $bad).'}}.']);
        }
        if ($encounter && $encounter->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['body' => 'O atendimento é de outro paciente.']);
        }

        $id = (string) Str::ulid();
        $code = strtoupper(Str::random(12));
        $issuedAt = now();
        $unit = $this->unitFor($encounter);
        $pdf = $this->renderPdf([
            'id' => $id, 'code' => $code, 'title' => $title, 'body' => $body, 'category' => $category,
            'issuedAt' => $issuedAt->copy()->timezone($unit->timezone ?? 'America/Sao_Paulo'),
            'tenant' => Tenant::query()->firstOrFail(), 'unit' => $unit, 'issuer' => $issuer,
            'patient' => $patient, 'values' => $this->values($patient, $encounter, $issuer),
            'primary' => TenantTheme::query()->value('primary_color') ?? TenantTheme::DEFAULT['primary'],
        ]);
        $path = sprintf('documents/%s/%s/%s.pdf', $this->context->requireId(), $issuedAt->format('Y'), $id);
        Storage::disk('local')->put($path, $pdf);

        try {
            $document = new IssuedDocument([
                'patient_id' => $patient->id, 'encounter_id' => $encounter?->id, 'template_id' => $template?->id,
                'template_version' => $template?->version, 'category' => $category, 'title' => $title, 'body' => $body,
                'pdf_path' => $path, 'pdf_sha256' => hash('sha256', $pdf), 'pdf_bytes' => strlen($pdf), 'verification_code' => $code,
                'issued_by' => $issuer->id, 'issuer_membership_id' => (string) $this->context->membership()?->id, 'issued_at' => $issuedAt,
            ]);
            $document->id = $id;
            $document->save();
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path); // sem registro, não fica PDF órfão

            throw $e;
        }
        $this->audit->record('document.issued', $issuer->id, 'document', $id, ['category' => $category, 'patient_id' => $patient->id]);

        return $document;
    }

    public function void(IssuedDocument $document, string $reason, User $actor): void
    {
        if ($document->isVoided()) {
            return;
        }
        if ($document->issued_by !== $actor->id && ! in_array('tenant_owner', $this->context->roles(), true)) {
            abort(403);
        }
        $document->voided_at = now();
        $document->voided_by = $actor->id;
        $document->void_reason = mb_substr($reason, 0, 200);
        $document->save();
        $this->audit->record('document.voided', $actor->id, 'document', $document->id);
    }

    /** Conteúdo do PDF guardado, conferindo a integridade (hash). */
    public function pdfContents(IssuedDocument $document): string
    {
        $bytes = Storage::disk('local')->get($document->pdf_path);
        if ($bytes === null || ! hash_equals($document->pdf_sha256, hash('sha256', $bytes))) {
            abort(500, 'Arquivo do documento ausente ou alterado.');
        }

        return $bytes;
    }

    /** @param array<string, mixed> $data */
    public function renderPdf(array $data): string
    {
        return $this->renderHtml(view('documents.pdf', $data)->render());
    }

    /** HTML (já escapado pela view) → PDF, com as mesmas restrições de segurança do dompdf. */
    public function renderHtml(string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);       // nunca busca nada fora do servidor
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(resource_path('pdf'));
        $options->setDefaultFont('DejaVu Sans');    // acentos do português
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function unitFor(?Encounter $encounter): ?Unit
    {
        $id = $encounter !== null ? $encounter->unit_id : $this->context->unitId();

        return ($id ? Unit::query()->find($id) : null) ?? Unit::query()->orderBy('name')->first();
    }
}
