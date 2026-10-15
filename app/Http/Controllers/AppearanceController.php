<?php

namespace App\Http\Controllers;

use App\Models\TenantTheme;
use App\Support\Audit;
use App\Support\Contrast;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AppearanceController extends Controller
{
    public function edit(): View
    {
        $theme = TenantTheme::query()->first();

        return view('settings.appearance', [
            'theme' => [
                'primary' => $theme->primary_color ?? TenantTheme::DEFAULT['primary'],
                'secondary' => $theme->secondary_color ?? TenantTheme::DEFAULT['secondary'],
                'mode' => $theme->mode ?? TenantTheme::DEFAULT['mode'],
            ],
            'default' => TenantTheme::DEFAULT,
        ]);
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        $aa = function (string $attr, mixed $value, Closure $fail) {
            if (! Contrast::passesAa((string) $value)) {
                $fail('Contraste insuficiente para texto sobre esta cor (mínimo 4,5:1). Escolha um tom mais escuro ou mais claro.');
            }
        };
        $data = $request->validate([
            'primary' => ['required', 'string', 'regex:'.Contrast::HEX, $aa],
            'secondary' => ['required', 'string', 'regex:'.Contrast::HEX, $aa],
            'mode' => ['required', 'in:light,dark,system'],
        ], ['primary.regex' => 'Use o formato #RRGGBB.', 'secondary.regex' => 'Use o formato #RRGGBB.']);

        $userId = (string) $request->user()?->getAuthIdentifier();
        $theme = TenantTheme::query()->firstOrNew();
        $theme->fill([
            'primary_color' => strtolower($data['primary']),
            'secondary_color' => strtolower($data['secondary']),
            'mode' => $data['mode'],
            'updated_by' => $userId,
        ])->save();
        $audit->record('tenant.theme_updated', $userId, 'tenant', $theme->tenant_id, $data);

        return redirect()->route('settings.appearance')->with('status', 'Aparência salva para toda a clínica.');
    }
}
