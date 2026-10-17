<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Log;

/**
 * "Firewall" de SQL — substitui, no MySQL, o papel do Row-Level Security do PostgreSQL.
 * Executa ANTES de cada consulta (Connection::beforeExecuting) e recusa DML/SELECT que toque tabela
 * de clínica quando:
 *  - não há clínica ativa (e não está em modo sistema);
 *  - o SQL não contém filtro/coluna `tenant_id` (ou, em `tenants`, o filtro por `id`);
 *  - nenhum parâmetro vinculado é o ID da clínica ativa (ex.: filtro com tenant_id de OUTRA clínica).
 *
 * É uma verificação conservadora e heurística (defesa em profundidade): o escopo automático
 * (BelongsToTenant) é a primeira barreira; FKs compostas no banco são a terceira.
 */
final class TenantQueryFirewall
{
    /** Tabelas cujas linhas pertencem a uma clínica (manter em sincronia com as migrações). */
    public const TENANT_TABLES = ['units', 'memberships', 'membership_units', 'tenant_themes', 'audit_events', 'invitations', 'guardians', 'patients', 'guardian_patient', 'appointment_types', 'appointments', 'encounters', 'encounter_diagnoses', 'encounter_addenda', 'patient_weights', 'patient_alerts', 'document_templates', 'issued_documents', 'attachments', 'vaccines', 'vaccinations'];

    public function __construct(private readonly TenantContext $context) {}

    /** @param array<int|string, mixed> $bindings */
    public function inspect(string $sql, array $bindings): void
    {
        if ($this->context->isSystem()) {
            return;
        }
        if (! preg_match('/^\s*\(?\s*(select|insert|update|delete|replace)\b/i', $sql)) {
            return; // DDL/transações/etc.
        }
        preg_match_all('/`([a-z_]+)`/', $sql, $m);
        $tables = array_unique($m[1]);
        $touchesTenantTables = array_intersect($tables, self::TENANT_TABLES) !== [];
        $touchesTenants = in_array('tenants', $tables, true) && preg_match('/\b(from|join|into|update)\s+`tenants`/i', $sql);
        if (! $touchesTenantTables && ! $touchesTenants) {
            return;
        }

        $tenantId = $this->context->id();
        if ($tenantId === null) {
            $this->deny('consulta a tabela de clínica sem clínica ativa', $sql);
        }
        if ($touchesTenantTables && ! str_contains($sql, '`tenant_id`')) {
            $this->deny('consulta a tabela de clínica sem filtro tenant_id', $sql);
        }
        $isInsert = (bool) preg_match('/^\s*(insert|replace)\b/i', $sql);
        if ($touchesTenants && ! $touchesTenantTables && ! $isInsert && ! preg_match('/`id`\s*(=|in\b)/i', $sql)) {
            $this->deny('consulta a tenants sem filtro por id', $sql);
        }
        if (! in_array($tenantId, $bindings, true)) {
            $this->deny('filtro de clínica diferente da clínica ativa', $sql);
        }
    }

    private function deny(string $reason, string $sql): never
    {
        // Registra só o formato do SQL (sem valores vinculados) — nunca dados de clínica nos logs.
        Log::critical('tenant_firewall_blocked', ['reason' => $reason, 'sql' => mb_substr($sql, 0, 300)]);
        if ($this->context->id() === null) {
            throw new TenantContextMissing($reason);
        }
        throw new CrossTenantViolation("Bloqueado pelo firewall de clínicas: {$reason}.");
    }
}
