<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtro obrigatório por clínica. Sem clínica ativa (e fora do modo sistema) a consulta é recusada.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof TenantOwned) {
            throw new CrossTenantViolation($model::class.' usa TenantScope sem implementar TenantOwned.');
        }
        $context = app(TenantContext::class);
        if ($context->isSystem()) {
            return;
        }
        $column = $model->qualifyColumn($model->tenantKeyColumn());
        $builder->where($column, '=', $context->requireId($model->getTable()));
    }
}
