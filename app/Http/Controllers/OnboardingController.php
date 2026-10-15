<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContextService;
use App\Support\Audit;
use App\Support\BrazilianDocument;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Cadastro de nova clínica/profissional (CLAUDE.md §2.1 A). Controlado por FAUVOVET_ALLOW_SELF_SIGNUP. */
final class OnboardingController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Audit $audit,
        private readonly ContextService $contexts,
    ) {}

    public function show(): View
    {
        abort_unless(config('fauvovet.allow_self_signup'), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('fauvovet.allow_self_signup'), 404);
        $data = $request->validate([
            'person_type' => ['required', 'in:PF,PJ'],
            'trade_name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'document' => ['nullable', 'string', 'max:20', function (string $attr, mixed $value, Closure $fail) use ($request) {
                $pf = $request->input('person_type') === 'PF';
                $valid = $pf ? BrazilianDocument::isValidCpf((string) $value) : BrazilianDocument::isValidCnpj((string) $value);
                if (! $valid) {
                    $fail($pf ? 'CPF inválido.' : 'CNPJ inválido.');
                }
            }],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_crmv' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
            'unit_name' => ['nullable', 'string', 'min:2', 'max:120'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $tenantId = (string) Str::ulid();
        try {
            $user = DB::transaction(function () use ($data, $email, $tenantId) {
                $user = User::create([
                    'name' => $data['owner_name'],
                    'email' => $email,
                    'crmv' => $data['owner_crmv'] ?? null,
                    'password' => $data['password'],
                ]);
                $this->context->runForTenant($tenantId, function () use ($data, $user, $tenantId) {
                    $tenant = new Tenant([
                        'trade_name' => $data['trade_name'],
                        'legal_name' => $data['legal_name'] ?? null,
                        'person_type' => $data['person_type'],
                        'document_number' => isset($data['document']) ? BrazilianDocument::digits($data['document']) : null,
                    ]);
                    $tenant->id = $tenantId;
                    $tenant->save();
                    $unit = Unit::create([
                        'name' => $data['unit_name'] ?? ($data['person_type'] === 'PF' ? 'Atendimento principal' : 'Matriz'),
                        'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
                    ]);
                    Membership::create(['user_id' => $user->id, 'roles' => ['tenant_owner'], 'status' => 'active']);
                    TenantTheme::create([
                        'primary_color' => TenantTheme::DEFAULT['primary'],
                        'secondary_color' => TenantTheme::DEFAULT['secondary'],
                        'mode' => TenantTheme::DEFAULT['mode'],
                        'updated_by' => $user->id,
                    ]);
                    $this->audit->record('tenant.created', $user->id, 'tenant', $tenantId, ['person_type' => $data['person_type'], 'unit_id' => $unit->id]);
                });

                return $user;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'users_email_unique')) {
                // Mensagem genérica: orienta login sem confirmar dados adicionais.
                throw ValidationException::withMessages(['email' => 'Não foi possível concluir o cadastro com este e-mail. Se você já possui conta, faça login.']);
            }
            throw $e;
        }

        Auth::login($user);
        $request->session()->regenerate();
        $membership = $this->contexts->membershipsFor($user)[0];
        $this->contexts->select($request, $user, $membership['membership_id'], null);

        return redirect()->route('dashboard')->with('status', 'Ambiente criado. Bem-vindo(a) ao FauvoVet!');
    }
}
