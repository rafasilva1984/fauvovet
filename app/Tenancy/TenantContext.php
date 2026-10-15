<?php

namespace App\Tenancy;

use App\Models\Membership;
use Closure;

/**
 * Clínica (tenant) ativa na requisição. Definida SOMENTE pelo middleware ResolveTenant a partir da sessão
 * validada (vínculo ativo do usuário autenticado) — nunca a partir de parâmetros enviados pelo cliente.
 *
 * "Modo sistema" desliga o escopo automático e deve ser usado apenas em operações anteriores à escolha
 * de clínica (login, listagem dos PRÓPRIOS vínculos) ou criação de clínica, sempre com filtros explícitos.
 */
final class TenantContext
{
    private ?string $tenantId = null;

    private ?Membership $membership = null;

    private ?string $unitId = null;

    private int $systemDepth = 0;

    public function set(Membership $membership, ?string $unitId): void
    {
        $this->tenantId = $membership->tenant_id;
        $this->membership = $membership;
        $this->unitId = $unitId;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->membership = null;
        $this->unitId = null;
    }

    /** Zera todo o estado (início de cada requisição; protege processos persistentes). */
    public function reset(): void
    {
        $this->clear();
        $this->systemDepth = 0;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function requireId(string $detail = ''): string
    {
        return $this->tenantId ?? throw new TenantContextMissing($detail);
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function unitId(): ?string
    {
        return $this->unitId;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->membership?->roleList() ?? [];
    }

    public function can(string $permission): bool
    {
        return $this->tenantId !== null && Roles::has($this->roles(), $permission);
    }

    public function isSystem(): bool
    {
        return $this->systemDepth > 0;
    }

    /**
     * Executa $fn sem escopo automático de clínica. Use com filtros explícitos e motivo documentado.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public function runAsSystem(Closure $fn): mixed
    {
        $this->systemDepth++;
        try {
            return $fn();
        } finally {
            $this->systemDepth--;
        }
    }

    /**
     * Executa $fn como se a clínica $tenantId estivesse ativa (ex.: criação da clínica no cadastro, seeds).
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public function runForTenant(string $tenantId, Closure $fn): mixed
    {
        [$prevId, $prevMembership, $prevUnit, $prevDepth] = [$this->tenantId, $this->membership, $this->unitId, $this->systemDepth];
        $this->tenantId = $tenantId;
        $this->membership = null;
        $this->unitId = null;
        $this->systemDepth = 0;
        try {
            return $fn();
        } finally {
            [$this->tenantId, $this->membership, $this->unitId, $this->systemDepth] = [$prevId, $prevMembership, $prevUnit, $prevDepth];
        }
    }
}
