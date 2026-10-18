<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use App\Tenancy\TenantContext;
use Illuminate\View\View;

/** Página Configurações: reúne num só lugar as telas de configuração que o papel de quem está logado pode abrir. */
final class SettingsController extends Controller
{
    public function index(TenantContext $context): View
    {
        $sections = Navigation::settings(fn (string $perm) => $context->can($perm));
        abort_if($sections === [], 403);

        return view('settings.index', ['sections' => $sections]);
    }
}
