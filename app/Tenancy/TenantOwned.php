<?php

namespace App\Tenancy;

/** Contrato dos models cujas linhas pertencem a uma clínica (implementado junto com BelongsToTenant). */
interface TenantOwned
{
    public function tenantKeyColumn(): string;
}
