<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Unit;
use App\Models\User;
use App\Services\TeamService;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Equipe da clínica ativa: lista, convites por link de uso único e edição de papéis/unidades/status. */
final class TeamController extends Controller
{
    public function __construct(private readonly TeamService $team, private readonly TenantContext $context) {}

    public function index(): View
    {
        $members = Membership::query()->with('user:id,name,email')->get()
            ->sortBy(fn (Membership $m) => [$m->status === 'active' ? 0 : 1, mb_strtolower($m->user->name)])->values();
        $invitations = $this->context->can('members:manage')
            ? Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->with('inviter:id,name')->orderByDesc('created_at')->limit(100)->get()
            : collect();

        return view('team.index', [
            'members' => $members,
            'invitations' => $invitations,
            'grantable' => $this->grantableRoles(),
            'selfId' => (string) auth()->id(),
            'isOwner' => $this->team->actorIsOwner(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Roles::all())],
        ], ['roles.required' => 'Escolha ao menos um papel.', 'roles.min' => 'Escolha ao menos um papel.']);

        /** @var User $actor */
        $actor = $request->user();
        $token = $this->team->invite($actor, $data['email'], array_values(array_unique($data['roles'])));

        return redirect()->route('team.index')
            ->with('status', 'Convite criado. Copie o link abaixo e envie para a pessoa convidada.')
            ->with('invite_link', route('invitations.show', ['token' => $token]))
            ->with('invite_email', mb_strtolower(trim($data['email'])));
    }

    public function revoke(Request $request, Invitation $invitation): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->team->revoke($actor, $invitation);

        return redirect()->route('team.index')->with('status', 'Convite cancelado. O link deixou de funcionar.');
    }

    /** Route model binding usa o escopo da clínica: vínculo de outra clínica → 404. */
    public function edit(Membership $membership): View
    {
        $membership->load('user:id,name,email');
        $restricted = DB::table('membership_units')->where('tenant_id', $this->context->requireId())
            ->where('membership_id', $membership->id)->pluck('unit_id')->all();

        return view('team.edit', [
            'member' => $membership,
            'units' => Unit::query()->orderBy('name')->get(['id', 'name']),
            'restricted' => $restricted,
            'grantable' => $this->grantableRoles(),
            'isSelf' => $membership->user_id === auth()->id(),
            'locked' => ! $this->team->actorIsOwner() && array_intersect($membership->roleList(), Roles::ADMIN_ROLES) !== [],
        ]);
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $tenantId = $this->context->requireId();
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Roles::all())],
            'units' => ['nullable', 'array'],
            'units.*' => ['string', 'size:26', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ], [
            'roles.required' => 'Escolha ao menos um papel.',
            'roles.min' => 'Escolha ao menos um papel.',
            'units.*.exists' => 'Unidade inválida.',
            'units.*.size' => 'Unidade inválida.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->team->update($actor, $membership, array_values(array_unique($data['roles'])), array_values($data['units'] ?? []), $data['status']);

        return redirect()->route('team.index')->with('status', 'Acesso de “'.$membership->user->name.'” atualizado.');
    }

    /** @return array<string, string> papéis que a pessoa logada pode conceder */
    private function grantableRoles(): array
    {
        $isOwner = $this->team->actorIsOwner();

        return array_filter(Roles::LABELS, fn (string $role) => $isOwner || ! in_array($role, Roles::ADMIN_ROLES, true), ARRAY_FILTER_USE_KEY);
    }
}
