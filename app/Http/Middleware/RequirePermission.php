<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('permission:units:create'). Negar por padrão; exige clínica ativa. */
final class RequirePermission
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($this->context->can($permission), 403, 'Seu papel não permite esta ação.');

        return $next($request);
    }
}
