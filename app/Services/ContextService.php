<?php

namespace App\Services;

use App\Http\Middleware\ResolveTenant;
use App\Models\Membership;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Vínculos do usuário e seleção explícita de clínica/unidade. */
final class ContextService
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    /**
     * Vínculos ATIVOS do próprio usuário em clínicas ativas, com as unidades permitidas.
     * Modo sistema justificado: a escolha de clínica precede o contexto; filtro explícito por user_id.
     *
     * @return list<array{membership_id: string, tenant_id: string, tenant_name: string, roles: list<string>, units: list<array{id: string, name: string}>}>
     */
    public function membershipsFor(User $user): array
    {
        return $this->context->runAsSystem(function () use ($user) {
            $memberships = Membership::query()
                ->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
                ->where('memberships.user_id', $user->id)
                ->where('memberships.status', 'active')
                ->where('tenants.status', 'active')
                ->orderBy('tenants.trade_name')
                ->get(['memberships.*', 'tenants.trade_name']);

            $result = [];
            foreach ($memberships as $m) {
                $restricted = DB::table('membership_units')->where('tenant_id', $m->tenant_id)->where('membership_id', $m->id)->pluck('unit_id')->all();
                $units = DB::table('units')->where('tenant_id', $m->tenant_id)
                    ->when($restricted !== [], fn ($q) => $q->whereIn('id', $restricted))
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => (string) $u->id, 'name' => (string) $u->name])->all();
                $result[] = [
                    'membership_id' => $m->id,
                    'tenant_id' => $m->tenant_id,
                    'tenant_name' => (string) $m->getAttribute('trade_name'),
                    'roles' => $m->roleList(),
                    'units' => $units,
                ];
            }

            return $result;
        });
    }

    /**
     * Seleciona clínica/unidade. A clínica é derivada do vínculo, que precisa pertencer ao usuário;
     * a unidade precisa estar entre as permitidas. Regenera o ID da sessão.
     *
     * @return bool false se o vínculo/unidade não for do usuário
     */
    public function select(Request $request, User $user, string $membershipId, ?string $unitId): bool
    {
        $choice = collect($this->membershipsFor($user))->firstWhere('membership_id', $membershipId);
        if (! $choice) {
            return false;
        }
        $unitIds = array_column($choice['units'], 'id');
        if ($unitId !== null && ! in_array($unitId, $unitIds, true)) {
            return false;
        }
        $unitId ??= count($unitIds) === 1 ? $unitIds[0] : null;

        $request->session()->put(ResolveTenant::SESSION_MEMBERSHIP, $choice['membership_id']);
        $request->session()->put(ResolveTenant::SESSION_UNIT, $unitId);
        $request->session()->regenerate();

        $this->context->runForTenant($choice['tenant_id'], fn () => $this->audit->record('auth.context_selected', $user->id, 'unit', $unitId));

        return true;
    }
}
