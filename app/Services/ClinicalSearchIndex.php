<?php

namespace App\Services;

use App\Files\TextExtractor;
use App\Models\DocumentTemplate;
use App\Models\Encounter;
use App\Models\ExamRequest;
use App\Models\ExamResultValue;
use App\Models\PatientAlert;
use App\Support\SearchText;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Indexação da busca clínica (ADR-017). O índice é DERIVADO do prontuário: cada alteração relevante (finalizar
 * atendimento, adendo, alerta, documento emitido/anulado, vacina registrada/anulada, anexo enviado/removido) reindexa o paciente na mesma
 * transação, então não há atraso. Rascunhos não entram (ainda não são registro assinado).
 * A versão permite reconstruir tudo automaticamente quando a regra de normalização mudar.
 */
final class ClinicalSearchIndex
{
    public const VERSION = 3; // subir quando a forma de indexar mudar: a próxima busca reconstrói tudo

    public function __construct(private readonly TenantContext $context) {}

    /** @return array{version: int, documents: int, indexed_at: Carbon}|null */
    public function status(): ?array
    {
        $row = DB::table('clinical_search_status')->where('tenant_id', $this->context->requireId())->first();

        return $row ? ['version' => (int) $row->version, 'documents' => (int) $row->documents, 'indexed_at' => Carbon::parse($row->indexed_at, 'UTC')] : null;
    }

    public function isCurrent(): bool
    {
        return ($this->status()['version'] ?? 0) === self::VERSION;
    }

    /** Reconstrói o índice da clínica inteira; idempotente. */
    public function rebuild(): int
    {
        $tenant = $this->context->requireId();
        DB::transaction(function () use ($tenant) {
            DB::table('clinical_search_documents')->where('tenant_id', $tenant)->delete(); // termos vão em cascata
            DB::table('patients')->where('tenant_id', $tenant)->orderBy('id')->select('id')
                ->chunk(200, fn ($rows) => $this->indexPatients($rows->pluck('id')->all()));
            $this->touchStatus(true);
        });

        return (int) ($this->status()['documents'] ?? 0);
    }

    /** Reindexa um paciente (chamado pelos serviços do prontuário). */
    public function reindexPatient(string $patientId): void
    {
        DB::table('clinical_search_documents')->where('tenant_id', $this->context->requireId())->where('patient_id', $patientId)->delete();
        $this->indexPatients([$patientId]);
        $this->touchStatus(false);
    }

    /** Recalcula a contagem depois de remoções em massa (ex.: dados de demonstração). */
    public function refreshCount(): void
    {
        if ($this->status() !== null) {
            $this->touchStatus(false);
        }
    }

    /** @param list<string> $patientIds */
    public function indexPatients(array $patientIds): void
    {
        if ($patientIds === []) {
            return;
        }
        $tenant = $this->context->requireId();
        $t = fn (string $table) => DB::table($table)->where("{$table}.tenant_id", $tenant);
        $docs = [];

        $encounters = $t('encounters')->whereIn('patient_id', $patientIds)->where('status', 'finalized')
            ->get(['id', 'patient_id', 'started_at', 'finalized_by', ...array_keys(Encounter::TEXT_FIELDS)])->keyBy('id');
        foreach ($encounters as $e) {
            foreach (Encounter::TEXT_FIELDS as $field => $label) {
                if (trim((string) $e->{$field}) !== '') {
                    $docs[] = [$e->patient_id, $e->id, 'encounter', $e->id, $field, $label, null, $e->started_at, $e->finalized_by, (string) $e->{$field}];
                }
            }
        }
        if ($encounters->isNotEmpty()) {
            foreach ($t('encounter_diagnoses')->whereIn('encounter_id', $encounters->keys()->all())->get() as $d) {
                $text = $d->description.($d->code ? " ({$d->code})" : '');
                $docs[] = [$d->patient_id, $d->encounter_id, 'diagnosis', $d->id, '', 'Diagnóstico', $d->status,
                    $encounters[$d->encounter_id]->started_at, $d->created_by, $text];
            }
            foreach ($t('encounter_addenda')->whereIn('encounter_id', $encounters->keys()->all())->get() as $a) {
                $docs[] = [$encounters[$a->encounter_id]->patient_id, $a->encounter_id, 'addendum', $a->id, '', 'Adendo', null, $a->created_at, $a->author_id, (string) $a->body];
            }
        }
        foreach ($t('patient_alerts')->whereIn('patient_id', $patientIds)->get() as $a) {
            $label = 'Alerta — '.(PatientAlert::KINDS[$a->kind] ?? $a->kind).($a->resolved_at ? ' (resolvido)' : '');
            $docs[] = [$a->patient_id, null, 'alert', $a->id, '', $label, null, $a->created_at, $a->created_by, (string) $a->description];
        }
        foreach ($t('issued_documents')->whereIn('patient_id', $patientIds)->whereNull('voided_at')->get() as $d) {
            $docs[] = [$d->patient_id, $d->encounter_id, 'document', $d->id, '', (DocumentTemplate::CATEGORIES[$d->category] ?? 'Documento').': '.$d->title, null,
                $d->issued_at, $d->issued_by, $d->title."\n".$d->body];
        }
        $vaccinations = $t('vaccinations')->join('vaccines', fn ($j) => $j->on('vaccines.id', '=', 'vaccinations.vaccine_id')->on('vaccines.tenant_id', '=', 'vaccinations.tenant_id'))
            ->whereIn('vaccinations.patient_id', $patientIds)->whereNull('vaccinations.voided_at')
            ->get(['vaccinations.id', 'vaccinations.patient_id', 'vaccinations.encounter_id', 'vaccinations.applied_on', 'vaccinations.dose_number',
                'vaccinations.lot', 'vaccinations.notes', 'vaccinations.created_by', 'vaccines.name']);
        foreach ($vaccinations as $v) {
            $text = "Vacina {$v->name}, dose {$v->dose_number}, lote {$v->lot}.".($v->notes ? ' '.$v->notes : '');
            $docs[] = [$v->patient_id, $v->encounter_id, 'vaccination', $v->id, '', 'Vacina', null, $v->applied_on, $v->created_by, $text];
        }

        // Exames: nome, pedido, resultado em texto e interpretação (o laudo anexado entra como anexo).
        $exams = $t('exam_requests')->join('exam_types', fn ($j) => $j->on('exam_types.id', '=', 'exam_requests.exam_type_id')->on('exam_types.tenant_id', '=', 'exam_requests.tenant_id'))
            ->whereIn('exam_requests.patient_id', $patientIds)->where('exam_requests.status', '!=', 'cancelled')
            ->get(['exam_requests.id', 'exam_requests.patient_id', 'exam_requests.encounter_id', 'exam_requests.status', 'exam_requests.request_notes',
                'exam_requests.result_text', 'exam_requests.interpretation', 'exam_requests.requested_at', 'exam_requests.received_at',
                'exam_requests.requested_by', 'exam_requests.reviewed_by', 'exam_types.name']);
        // Valores estruturados: "Creatinina: 2,3 mg/dL (acima da faixa)" — a situação é só a comparação com a faixa.
        $structured = $t('exam_result_values')->join('exam_analytes', fn ($j) => $j->on('exam_analytes.id', '=', 'exam_result_values.analyte_id')->on('exam_analytes.tenant_id', '=', 'exam_result_values.tenant_id'))
            ->whereIn('exam_result_values.patient_id', $patientIds)->orderBy('exam_analytes.position')
            ->get(['exam_result_values.exam_request_id', 'exam_result_values.value', 'exam_result_values.value_text', 'exam_result_values.unit', 'exam_result_values.flag', 'exam_analytes.name'])
            ->groupBy('exam_request_id')
            ->map(fn ($rows) => $rows->map(fn ($v) => $v->name.': '.($v->value !== null ? ExamResultValue::fmt($v->value) : $v->value_text).($v->unit ? ' '.$v->unit : '')
                .(in_array($v->flag, ['low', 'high'], true) ? ' ('.mb_strtolower(ExamResultValue::FLAGS[$v->flag]).')' : ''))->join('; '));
        foreach ($exams as $x) {
            $body = trim($x->name.($x->request_notes ? "\nPedido: ".$x->request_notes : '').($structured->has($x->id) ? "\nValores: ".$structured->get($x->id) : '').($x->result_text ? "\nResultado: ".$x->result_text : '')
                .($x->interpretation ? "\nInterpretação: ".$x->interpretation : ''));
            $docs[] = [$x->patient_id, $x->encounter_id, 'exam', $x->id, '', 'Exame: '.$x->name.' — '.(ExamRequest::STATUS[$x->status] ?? $x->status), null,
                $x->received_at ?? $x->requested_at, $x->reviewed_by ?? $x->requested_by, $body];
        }

        // Anexos: texto extraído (PDF/DOCX) quando houver; senão só título/descrição, sinalizando "sem texto pesquisável".
        foreach ($t('attachments')->whereIn('patient_id', $patientIds)->whereNull('removed_at')->get() as $a) {
            $readable = $a->text_status === 'indexed';
            $label = 'Anexo '.strtoupper((string) $a->extension).': '.$a->title.($readable ? '' : ' — '.(TextExtractor::STATUS[$a->text_status] ?? 'texto ainda não lido'));
            // O título entra pelo rótulo (também indexado); o trecho mostra o conteúdo do arquivo.
            $body = $readable ? $a->extracted_text.($a->description ? "\n".$a->description : '') : $a->title.($a->description ? "\n".$a->description : '');
            $docs[] = [$a->patient_id, $a->encounter_id, 'attachment', $a->id, '', $label, null, $a->created_at, $a->uploaded_by, $body];
        }

        $now = now();
        $rows = $terms = [];
        foreach ($docs as [$patient, $encounter, $type, $source, $field, $label, $dx, $when, $author, $body]) {
            $id = strtolower((string) Str::ulid());
            $rows[] = ['id' => $id, 'tenant_id' => $tenant, 'patient_id' => $patient, 'encounter_id' => $encounter, 'source_type' => $type,
                'source_id' => $source, 'field' => $field, 'label' => mb_substr($label, 0, 120), 'dx_status' => $dx, 'occurred_at' => $when,
                'author_id' => $author, 'body' => $body, 'normalized' => SearchText::normalize($body), 'indexed_at' => $now];
            foreach (SearchText::terms($label.' '.$body) as $term) {
                $terms[] = ['tenant_id' => $tenant, 'term' => $term, 'document_id' => $id];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('clinical_search_documents')->insert($chunk);
        }
        foreach (array_chunk($terms, 1000) as $chunk) {
            DB::table('clinical_search_terms')->insert($chunk);
        }
    }

    /** Atualiza contagem/data; a versão só sobe numa reconstrução completa (um paciente sozinho não "atualiza" a clínica). */
    private function touchStatus(bool $full): void
    {
        $tenant = $this->context->requireId();
        $count = DB::table('clinical_search_documents')->where('tenant_id', $tenant)->count();
        $exists = DB::table('clinical_search_status')->where('tenant_id', $tenant)->exists();
        if ($exists) {
            DB::table('clinical_search_status')->where('tenant_id', $tenant)
                ->update(['documents' => $count, 'indexed_at' => now()] + ($full ? ['version' => self::VERSION] : []));
        } else {
            DB::table('clinical_search_status')->insert(['tenant_id' => $tenant, 'version' => $full ? self::VERSION : 0, 'documents' => $count, 'indexed_at' => now()]);
        }
    }
}
