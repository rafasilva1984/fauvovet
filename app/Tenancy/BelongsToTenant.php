<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Para models com dados de uma clínica. Garante:
 *  - SELECT/UPDATE/DELETE via Eloquent sempre filtrados pela clínica ativa (TenantScope);
 *  - INSERT recebe tenant_id da clínica ativa; valor divergente é recusado;
 *  - tenant_id nunca muda após criado;
 *  - save()/delete() de instância incluem tenant_id no WHERE (não só a chave primária).
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $context = app(TenantContext::class);
            $column = $model->tenantKeyColumn();
            $current = $context->id();
            if ($current === null) {
                if (! $context->isSystem() || empty($model->{$column})) {
                    throw new TenantContextMissing('criação de '.$model->getTable());
                }

                return; // modo sistema com tenant_id explícito (ex.: seeds)
            }
            if (! empty($model->{$column}) && $model->{$column} !== $current) {
                throw new CrossTenantViolation("Tentativa de criar {$model->getTable()} em outra clínica.");
            }
            $model->{$column} = $current;
        });

        static::updating(function ($model): void {
            if ($model->isDirty($model->tenantKeyColumn())) {
                throw new CrossTenantViolation("tenant_id de {$model->getTable()} não pode ser alterado.");
            }
        });
    }

    public function tenantKeyColumn(): string
    {
        return 'tenant_id';
    }

    /** Inclui tenant_id no WHERE de UPDATE/DELETE da instância (defesa em profundidade + firewall de SQL). */
    protected function setKeysForSaveQuery($query): Builder
    {
        parent::setKeysForSaveQuery($query);
        $column = $this->tenantKeyColumn();
        if ($column !== $this->getKeyName()) {
            $query->where($column, '=', $this->getOriginal($column) ?? $this->getAttribute($column));
        }

        return $query;
    }
}
