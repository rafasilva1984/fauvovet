<?php

namespace App\Support;

/**
 * Frases do feed "Atividade recente" para cada código de auditoria. Leituras, downloads e buscas continuam na
 * trilha de auditoria, mas ficam fora do feed (só fariam ruído).
 */
final class AuditLabels
{
    /** Eventos de consulta: auditados, mas fora do feed. */
    public const QUIET = [
        'auth.context_selected', 'encounter.viewed', 'patient.clinical_viewed', 'exam.viewed', 'document.viewed', 'document.downloaded',
        'attachment.downloaded', 'attachment.text_extracted', 'clinical.search', 'patient.clinical_search', 'clinical_case.viewed',
        'document_template.original_downloaded', 'document_background.original_downloaded', 'vaccination.card_exported', 'prescription.issued',
    ];

    private const LABELS = [
        'tenant.created' => 'criou a clínica',
        'tenant.theme_updated' => 'alterou a aparência',
        'unit.created' => 'criou uma unidade',
        'member.invited' => 'convidou uma pessoa',
        'member.updated' => 'alterou um acesso',
        'invitation.accepted' => 'aceitou um convite',
        'invitation.revoked' => 'cancelou um convite',
        'patient.created' => 'cadastrou um paciente',
        'patient.updated' => 'atualizou um paciente',
        'patient.deceased' => 'registrou um óbito',
        'patient.guardian_linked' => 'vinculou um responsável',
        'patient.guardian_unlinked' => 'desvinculou um responsável',
        'patient.alert_added' => 'registrou um alerta clínico',
        'patient.alert_resolved' => 'resolveu um alerta clínico',
        'guardian.created' => 'cadastrou um responsável',
        'guardian.updated' => 'atualizou um responsável',
        'appointment.created' => 'agendou um atendimento',
        'appointment.updated' => 'reagendou um atendimento',
        'appointment.status_changed' => 'mudou a situação de um agendamento',
        'appointment_type.created' => 'criou um tipo de atendimento',
        'appointment_type.updated' => 'alterou um tipo de atendimento',
        'encounter.created' => 'abriu um atendimento',
        'encounter.updated' => 'editou um rascunho de prontuário',
        'encounter.finalized' => 'finalizou e assinou um atendimento',
        'encounter.addendum_added' => 'registrou um adendo',
        'vaccination.registered' => 'registrou uma vacina',
        'vaccination.voided' => 'anulou uma aplicação de vacina',
        'vaccine.created' => 'cadastrou uma vacina no catálogo',
        'vaccine.updated' => 'alterou uma vacina do catálogo',
        'exam.requested' => 'pediu um exame',
        'exam.status_changed' => 'atualizou a situação de um exame',
        'exam.result_registered' => 'registrou o resultado de um exame',
        'exam.values_recorded' => 'registrou valores de um exame',
        'exam.reviewed' => 'revisou um exame',
        'exam.cancelled' => 'cancelou um exame',
        'exam_type.created' => 'cadastrou um exame no catálogo',
        'exam_type.updated' => 'alterou um exame do catálogo',
        'exam_analyte.saved' => 'ajustou um parâmetro de exame',
        'exam_range.saved' => 'cadastrou uma faixa de referência',
        'exam_range.removed' => 'removeu uma faixa de referência',
        'document.issued' => 'emitiu um documento',
        'prescription.issued' => 'emitiu uma receita',
        'medication.created' => 'cadastrou um medicamento no catálogo',
        'medication.updated' => 'alterou um medicamento do catálogo',
        'document.voided' => 'anulou um documento',
        'document_template.created' => 'criou um modelo de documento',
        'document_template.updated' => 'alterou um modelo de documento',
        'document_template.imported' => 'importou um modelo de documento',
        'document_background.uploaded' => 'enviou um papel timbrado ou receituário',
        'document_background.margins_updated' => 'ajustou as margens de um fundo de documento',
        'document_background.retired' => 'removeu um fundo de documento',
        'attachment.uploaded' => 'enviou um anexo',
        'attachment.removed' => 'removeu um anexo',
        'clinical.search_reindexed' => 'reconstruiu o índice da busca',
        'saved_search.created' => 'salvou uma pesquisa',
        'saved_search.deleted' => 'excluiu uma pesquisa salva',
        'search_synonym.created' => 'cadastrou um sinônimo da busca',
        'search_synonym.updated' => 'alterou um sinônimo da busca',
        'search_synonym.deleted' => 'excluiu um sinônimo da busca',
        'clinical_case.created' => 'criou um caso científico',
        'clinical_case.consent_recorded' => 'registrou um consentimento de caso científico',
        'clinical_case.exported' => 'exportou um caso científico anonimizado',
        'demo_data.loaded' => 'carregou os dados de demonstração',
        'demo_data.refreshed' => 'atualizou os dados de demonstração',
        'demo_data.removed' => 'removeu os dados de demonstração',
    ];

    private const ICONS = ['vaccination' => 'syringe', 'medication' => 'pill', 'prescription' => 'pill', 'vaccine' => 'syringe', 'encounter' => 'stethoscope', 'appointment' => 'calendar', 'appointment_type' => 'calendar',
        'patient' => 'paw', 'guardian' => 'user', 'member' => 'users', 'invitation' => 'mail', 'unit' => 'building', 'exam' => 'flask', 'exam_type' => 'flask',
        'exam_analyte' => 'flask', 'exam_range' => 'flask', 'document' => 'file-text', 'document_template' => 'copy', 'document_background' => 'palette',
        'attachment' => 'copy', 'clinical_case' => 'file-text', 'tenant' => 'sparkles', 'auth' => 'shield'];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? 'fez uma alteração';
    }

    public static function icon(string $action): string
    {
        return self::ICONS[strtok($action, '.')] ?? 'sparkles';
    }
}
