<?php

namespace App\Tenancy;

use RuntimeException;

/** Consulta/gravação de dado de clínica sem clínica ativa: bloqueada (falha fechada). */
final class TenantContextMissing extends RuntimeException
{
    public function __construct(string $detail = '')
    {
        parent::__construct('Operação em dado de clínica sem clínica ativa'.($detail ? ": {$detail}" : '.'));
    }
}
