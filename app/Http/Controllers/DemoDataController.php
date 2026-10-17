<?php

namespace App\Http\Controllers;

use App\Services\DemoDataService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Dados de demonstração fictícios na clínica ativa (só titular; só com FAUVOVET_DEMO_DATA=true — ADR-016).
 * Desligado, as rotas respondem 404 como se não existissem.
 */
final class DemoDataController extends Controller
{
    public function __construct(private readonly DemoDataService $demo, private readonly Audit $audit) {}

    private function enabled(): void
    {
        abort_unless((bool) config('fauvovet.demo_data'), 404);
    }

    public function show(): View
    {
        $this->enabled();

        return view('settings.demo-data', ['counts' => $this->demo->counts()]);
    }

    public function load(Request $request): RedirectResponse
    {
        $this->enabled();
        $userId = (string) $request->user()?->getAuthIdentifier();
        try {
            $r = $this->demo->load($userId);
        } catch (RuntimeException $e) {
            return back()->withErrors(['demo' => $e->getMessage()]);
        }
        $this->audit->record('demo_data.loaded', $userId, 'tenant', null, $r);

        return redirect()->route('settings.demo-data')->with('status', "Demonstração carregada: {$r['patients']} pacientes, {$r['encounters']} atendimentos, {$r['vaccinations']} vacinas, {$r['appointments']} agendamentos e {$r['cases']} casos científicos.");
    }

    public function remove(Request $request): RedirectResponse
    {
        $this->enabled();
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Marque a confirmação para remover.']);
        $r = $this->demo->remove();
        $this->audit->record('demo_data.removed', (string) $request->user()?->getAuthIdentifier(), 'tenant', null, $r);

        return redirect()->route('settings.demo-data')->with('status', "Demonstração removida: {$r['patients']} pacientes, {$r['guardians']} responsáveis e {$r['cases']} casos.");
    }
}
