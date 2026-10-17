<?php

namespace App\Http\Controllers;

use App\Models\AppointmentType;
use App\Services\ScheduleService;
use App\Support\Audit;
use App\Support\Contrast;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tipos de atendimento da clínica (duração padrão e cor na agenda). Desativar em vez de apagar: há histórico. */
final class AppointmentTypeController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    public function index(ScheduleService $schedule): View
    {
        $schedule->ensureDefaultTypes();

        return view('settings.appointment-types', ['types' => AppointmentType::query()->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = AppointmentType::create([...$this->validated($request), 'active' => true]);
        $this->audit->record('appointment_type.created', (string) $request->user()?->getAuthIdentifier(), 'appointment_type', $type->id);

        return redirect()->route('settings.appointment-types')->with('status', "Tipo “{$type->name}” criado.");
    }

    public function edit(AppointmentType $appointmentType): View
    {
        return view('settings.appointment-type-edit', ['type' => $appointmentType]);
    }

    public function update(Request $request, AppointmentType $appointmentType): RedirectResponse
    {
        $data = $this->validated($request, $appointmentType);
        $appointmentType->fill([...$data, 'active' => $request->boolean('active')])->save();
        $this->audit->record('appointment_type.updated', (string) $request->user()?->getAuthIdentifier(), 'appointment_type', $appointmentType->id);

        return redirect()->route('settings.appointment-types')->with('status', "Tipo “{$appointmentType->name}” salvo.");
    }

    /** @return array{name: string, duration_minutes: int, color: string} */
    private function validated(Request $request, ?AppointmentType $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80',
                Rule::unique('appointment_types', 'name')->where('tenant_id', $this->context->requireId())->ignore($current?->id)],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/', function (string $attr, mixed $value, Closure $fail) {
                // Componente gráfico (borda/marcador) sobre fundo claro: WCAG 1.4.11 pede 3:1.
                if (Contrast::ratio((string) $value, '#ffffff') < 3) {
                    $fail('Cor muito clara: ela quase não aparece na agenda. Use uma cor mais escura (contraste mínimo 3:1 com o branco).');
                }
            }],
        ], ['name.unique' => 'Já existe um tipo com este nome.', 'color.regex' => 'Escolha uma cor válida.']);

        return ['name' => trim($data['name']), 'duration_minutes' => (int) $data['duration_minutes'], 'color' => strtolower($data['color'])];
    }
}
