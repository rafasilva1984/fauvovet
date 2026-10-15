<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', ['units' => Unit::query()->orderBy('name')->limit(500)->get()]);
    }

    /** Route model binding usa o escopo da clínica: unidade de outra clínica → 404 (não revela existência). */
    public function show(Unit $unit): View
    {
        return view('units.show', ['unit' => $unit]);
    }

    public function store(Request $request, TenantContext $context, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120',
                Rule::unique('units', 'name')->where('tenant_id', $context->requireId())],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'address_line' => ['nullable', 'string', 'max:200'],
            'timezone' => ['nullable', 'timezone:all'],
        ], ['name.unique' => 'Já existe uma unidade com este nome.', 'state.regex' => 'UF deve ter 2 letras.']);

        // Somente campos permitidos (tenant_id do formulário é ignorado; vem da sessão).
        $unit = Unit::create([
            'name' => $data['name'],
            'city' => $data['city'] ?? null,
            'state' => isset($data['state']) ? strtoupper($data['state']) : null,
            'address_line' => $data['address_line'] ?? null,
            'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
        ]);
        $audit->record('unit.created', (string) $request->user()?->getAuthIdentifier(), 'unit', $unit->id);

        return redirect()->route('units.index')->with('status', "Unidade “{$unit->name}” criada.");
    }
}
