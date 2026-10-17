<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Meu perfil": nome e CRMV de quem está logado (o CRMV sai nas receitas e atestados). */
final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'crmv' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9 .\/-]+$/'],
        ], ['crmv.regex' => 'CRMV: use letras, números, espaço, ponto, barra ou hífen (ex.: CRMV-SP 12345).']);
        /** @var User $user */
        $user = $request->user();
        $user->fill(['name' => trim($data['name']), 'crmv' => isset($data['crmv']) ? strtoupper(trim($data['crmv'])) : null]);
        $changed = array_keys($user->getDirty());
        $user->save();
        $audit->recordPlatform('user.profile_updated', $user->id, ['fields' => $changed]);

        return redirect()->route('profile.edit')->with('status', 'Perfil atualizado.');
    }
}
