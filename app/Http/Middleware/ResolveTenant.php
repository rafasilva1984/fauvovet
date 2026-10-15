<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Unit;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a clínica ativa a partir da SESSÃO (servidor), revalidando a cada requisição que o vínculo
 * pertence ao usuário autenticado, está ativo e que a clínica está ativa. Parâmetros do cliente
 * (ex.: tenant_id em formulário/URL/cabeçalho) nunca são considerados.
 */
final class ResolveTenant
{
    public const SESSION_MEMBERSHIP = 'fv.membership_id';

    public const SESSION_UNIT = 'fv.unit_id';

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $membershipId = $request->session()->get(self::SESSION_MEMBERSHIP);
        if (! $user || ! is_string($membershipId)) {
            return redirect()->route('context.show');
        }

        // Modo sistema com filtros explícitos: id do vínculo + dono = usuário autenticado + status ativo.
        $membership = $this->context->runAsSystem(fn () => Membership::query()
            ->whereKey($membershipId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')
            ->whereIn('tenant_id', fn ($q) => $q->select('id')->from('tenants')->where('status', 'active'))
            ->first());

        if (! $membership) {
            $request->session()->forget([self::SESSION_MEMBERSHIP, self::SESSION_UNIT]);

            return redirect()->route('context.show')->with('status', 'Seu acesso a esta clínica não está mais ativo.');
        }

        $unitId = $request->session()->get(self::SESSION_UNIT);
        $this->context->set($membership, null);
        if (is_string($unitId) && Unit::query()->whereKey($unitId)->exists()) {
            $this->context->set($membership, $unitId);
        } else {
            $request->session()->forget(self::SESSION_UNIT);
        }

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
