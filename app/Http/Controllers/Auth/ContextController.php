<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ContextService;
use App\Tenancy\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContextController extends Controller
{
    public function __construct(private readonly ContextService $contexts) {}

    public function show(Request $request): View
    {
        $memberships = array_map(function (array $m) {
            $m['role_labels'] = array_map([Roles::class, 'label'], $m['roles']);

            return $m;
        }, $this->contexts->membershipsFor($request->user()));

        return view('auth.context', [
            'memberships' => $memberships,
            'current' => $request->session()->get('fv.membership_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'membership_id' => ['required', 'string', 'size:26'],
            'unit_id' => ['nullable', 'string', 'size:26'],
        ]);
        $ok = $this->contexts->select($request, $request->user(), $data['membership_id'], $data['unit_id'] ?? null);
        abort_unless($ok, 403, 'Vínculo ou unidade indisponível para o seu usuário.');

        return redirect()->route('dashboard');
    }
}
