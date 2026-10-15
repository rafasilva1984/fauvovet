<?php

namespace App\Tenancy;

use RuntimeException;

/** Tentativa de ler/gravar dado de outra clínica ou SQL sem filtro de clínica. Nunca deve ocorrer. */
final class CrossTenantViolation extends RuntimeException {}
