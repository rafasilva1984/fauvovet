<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ContextService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    private const GENERIC_ERROR = 'E-mail ou senha incorretos.';

    private static ?string $dummyHash = null;

    public function __construct(private readonly Audit $audit, private readonly ContextService $contexts) {}

    public function show(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            Hash::check($data['password'], self::dummyHash()); // tempo de resposta equivalente (anti-enumeração)
            $this->audit->recordPlatform('auth.login_failed', null, ['reason' => 'unknown_email', 'email_fp' => Audit::emailFingerprint($email)]);
            throw ValidationException::withMessages(['email' => self::GENERIC_ERROR]);
        }
        if ($user->isLocked()) {
            $this->audit->recordPlatform('auth.login_blocked', $user->id, ['reason' => 'locked']);
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.']);
        }

        $ok = $user->status === 'active' && Hash::check($data['password'], $user->password);
        if (! $ok) {
            $user->failed_login_count++;
            if ($user->failed_login_count >= (int) config('fauvovet.login.max_failures')) {
                $user->locked_until = now()->addMinutes((int) config('fauvovet.login.lock_minutes'));
                $user->failed_login_count = 0;
            }
            $user->save();
            $this->audit->recordPlatform('auth.login_failed', $user->id, ['reason' => $user->status === 'active' ? 'bad_password' : 'disabled']);
            throw ValidationException::withMessages(['email' => self::GENERIC_ERROR]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $data['password'];
        }
        $user->failed_login_count = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();
        $this->audit->recordPlatform('auth.login', $user->id);

        // Contexto automático só quando não há escolha a fazer (1 vínculo com no máximo 1 unidade).
        $memberships = $this->contexts->membershipsFor($user);
        if (count($memberships) === 1 && count($memberships[0]['units']) <= 1) {
            $this->contexts->select($request, $user, $memberships[0]['membership_id'], null);

            return redirect()->route('dashboard');
        }

        return redirect()->route('context.show');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $this->audit->recordPlatform('auth.logout', $user->getAuthIdentifier());
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(32));
    }
}
