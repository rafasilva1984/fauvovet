<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras da equipe de uma clínica (convites, papéis, unidades e status). Executa sempre dentro da clínica ativa,
 * exceto o aceite de convite, que entra explicitamente na clínica do convite (runForTenant).
 *
 * Regras de proteção:
 *  - papéis administrativos (titular/gestão) só são concedidos, retirados ou editados por um titular;
 *  - ninguém altera o próprio vínculo (evita perder o acesso por engano);
 *  - a clínica nunca fica sem ao menos um titular ativo.
 */
final class TeamService
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function actorIsOwner(): bool
    {
        return in_array('tenant_owner', $this->context->roles(), true);
    }

    /**
     * Cria um convite e devolve o token em claro (exibido uma única vez). Convites pendentes anteriores para o
     * mesmo e-mail são revogados.
     *
     * @param  list<string>  $roles
     */
    public function invite(User $actor, string $email, array $roles): string
    {
        $email = mb_strtolower(trim($email));
        $this->assertCanGrant($roles);

        $alreadyActive = Membership::query()->where('status', 'active')
            ->whereIn('user_id', User::query()->select('id')->where('email', $email))
            ->exists();
        if ($alreadyActive) {
            throw ValidationException::withMessages(['email' => 'Esta pessoa já faz parte da equipe ativa.']);
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        DB::transaction(function () use ($actor, $email, $roles, $token) {
            $this->pendingFor($email)->each(fn (Invitation $old) => $this->markRevoked($old, $actor));
            $invitation = Invitation::create([
                'email' => $email,
                'roles' => $roles,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(Invitation::TTL_DAYS),
            ]);
            $this->audit->record('member.invited', $actor->id, 'invitation', $invitation->id, [
                'roles' => $roles,
                'email_fp' => Audit::emailFingerprint($email),
            ]);
        });

        return $token;
    }

    public function revoke(User $actor, Invitation $invitation): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'Este convite não está mais pendente.']);
        }
        if (! $this->actorIsOwner() && array_intersect($invitation->roleList(), Roles::ADMIN_ROLES) !== []) {
            abort(403);
        }
        $this->markRevoked($invitation, $actor);
        $this->audit->record('invitation.revoked', $actor->id, 'invitation', $invitation->id);
    }

    /**
     * Atualiza papéis, unidades permitidas (vazio = todas) e status de um vínculo da clínica ativa.
     *
     * @param  list<string>  $roles
     * @param  list<string>  $unitIds  já validados como unidades da clínica ativa
     */
    public function update(User $actor, Membership $membership, array $roles, array $unitIds, string $status): void
    {
        $this->assertCanManage($actor, $membership);
        $this->assertCanGrant($roles);

        DB::transaction(function () use ($actor, $membership, $roles, $unitIds, $status) {
            $before = ['roles' => $membership->roleList(), 'status' => $membership->status];
            $membership->roles = $roles;
            $membership->status = $status;
            $membership->save();

            $tenantId = $this->context->requireId();
            DB::table('membership_units')->where('tenant_id', $tenantId)->where('membership_id', $membership->id)->delete();
            foreach (array_unique($unitIds) as $unitId) {
                DB::table('membership_units')->insert(['tenant_id' => $tenantId, 'membership_id' => $membership->id, 'unit_id' => $unitId]);
            }

            $this->assertStillHasOwner();
            $this->audit->record('member.updated', $actor->id, 'membership', $membership->id, [
                'before' => $before,
                'after' => ['roles' => $roles, 'status' => $status],
                'units_restricted' => count($unitIds),
            ]);
        });
    }

    /**
     * Aceita um convite pendente para o usuário (cujo e-mail já foi conferido pelo chamador). Reativa um vínculo
     * existente, se houver, com os papéis do convite. Retorna o id do vínculo.
     */
    public function accept(Invitation $invitation, User $user): string
    {
        return $this->context->runForTenant($invitation->tenant_id, function () use ($invitation, $user) {
            return DB::transaction(function () use ($invitation, $user) {
                /** @var Invitation $fresh */
                $fresh = Invitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                if (! $fresh->isPending()) {
                    throw ValidationException::withMessages(['invitation' => 'Este convite não está mais disponível.']);
                }
                $membership = Membership::query()->where('user_id', $user->id)->first()
                    ?? new Membership(['user_id' => $user->id]);
                $membership->fill(['roles' => $fresh->roleList(), 'status' => 'active'])->save();

                $fresh->accepted_at = now();
                $fresh->accepted_by = $user->id;
                $fresh->save();
                $this->audit->record('invitation.accepted', $user->id, 'membership', $membership->id, ['invitation_id' => $fresh->id]);

                return $membership->id;
            });
        });
    }

    /** @param  list<string>  $roles */
    private function assertCanGrant(array $roles): void
    {
        if (array_intersect($roles, Roles::ADMIN_ROLES) !== [] && ! $this->actorIsOwner()) {
            throw ValidationException::withMessages(['roles' => 'Somente o titular pode conceder os papéis Titular ou Gestão.']);
        }
    }

    private function assertCanManage(User $actor, Membership $membership): void
    {
        if ($membership->user_id === $actor->id) {
            throw ValidationException::withMessages(['roles' => 'Você não pode alterar o seu próprio acesso. Peça a outro titular.']);
        }
        if (array_intersect($membership->roleList(), Roles::ADMIN_ROLES) !== [] && ! $this->actorIsOwner()) {
            abort(403);
        }
    }

    private function assertStillHasOwner(): void
    {
        $owners = Membership::query()->where('status', 'active')->get()
            ->filter(fn (Membership $m) => in_array('tenant_owner', $m->roleList(), true));
        if ($owners->isEmpty()) {
            throw ValidationException::withMessages(['roles' => 'A clínica precisa de ao menos um titular ativo.']);
        }
    }

    /** @return Collection<int, Invitation> */
    private function pendingFor(string $email)
    {
        return Invitation::query()->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->get();
    }

    private function markRevoked(Invitation $invitation, User $actor): void
    {
        $invitation->revoked_at = now();
        $invitation->revoked_by = $actor->id;
        $invitation->save();
    }
}
