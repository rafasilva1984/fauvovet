<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\ContextService;
use App\Services\TeamService;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Aceite de convite pelo link de uso único (/convite/{token}). Público, com limite de tentativas.
 * Convite inexistente, expirado, cancelado ou já usado → a mesma página 404 (não revela qual caso).
 */
final class InvitationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TeamService $team,
        private readonly ContextService $contexts,
        private readonly Audit $audit,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->find($token);
        if (! $invitation) {
            return response()->view('invitations.invalid', [], 404);
        }
        $user = $request->user();
        $account = $user ?? $this->accountFor($invitation->email);
        $state = match (true) {
            $user !== null && $user->email !== $invitation->email => 'wrong_account',
            $user !== null => 'confirm',
            $account !== null => 'login',
            default => 'register',
        };
        if ($state === 'login') {
            // Depois do login, volta para este convite.
            $request->session()->put('url.intended', $request->url());
        }

        return response()->view('invitations.show', [
            'invitation' => $invitation,
            'tenantName' => $this->tenantName($invitation),
            'state' => $state,
            'token' => $token,
        ], $state === 'wrong_account' ? 403 : 200);
    }

    public function accept(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->find($token);
        if (! $invitation) {
            return response()->view('invitations.invalid', [], 404);
        }

        $user = $request->user();
        if ($user !== null) {
            abort_unless($user->email === $invitation->email && $user->status === 'active', 403);
        } else {
            if ($this->accountFor($invitation->email)) {
                return redirect()->route('invitations.show', ['token' => $token]);
            }
            $data = $request->validate([
                'name' => ['required', 'string', 'min:2', 'max:120'],
                'crmv' => ['nullable', 'string', 'max:30'],
                'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
            ]);
            try {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'crmv' => $data['crmv'] ?? null,
                    'password' => $data['password'],
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'users_email_unique')) {
                    return redirect()->route('invitations.show', ['token' => $token]);
                }
                throw $e;
            }
            $this->audit->recordPlatform('auth.account_created', $user->id, ['via' => 'invitation']);
            Auth::login($user);
            $request->session()->regenerate();
        }

        $membershipId = $this->team->accept($invitation, $user);
        $request->session()->forget('url.intended');
        if (! $this->contexts->select($request, $user, $membershipId, null)) {
            return redirect()->route('context.show');
        }

        return redirect()->route('dashboard')->with('status', 'Convite aceito. Bem-vindo(a) à equipe de '.$this->tenantName($invitation).'!');
    }

    /** Busca por hash em modo sistema (o convite ainda não tem clínica ativa); só pendentes de clínica ativa. */
    private function find(string $token): ?Invitation
    {
        /** @var Invitation|null $invitation */
        $invitation = $this->context->runAsSystem(fn () => Invitation::query()
            ->where('token_hash', Invitation::hashToken($token))->first());

        return $invitation?->isPending() && $this->tenantName($invitation) !== '' ? $invitation : null;
    }

    private function accountFor(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    private function tenantName(Invitation $invitation): string
    {
        return (string) $this->context->runAsSystem(fn () => DB::table('tenants')->where('id', $invitation->tenant_id)->where('status', 'active')->value('trade_name'));
    }
}
