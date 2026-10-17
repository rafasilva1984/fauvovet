<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\User;
use App\Services\ClinicalRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Alertas clínicos do paciente (alergias etc.). Resolver em vez de apagar; tudo auditado. */
final class PatientAlertController extends Controller
{
    public function __construct(private readonly ClinicalRecordService $records) {}

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PatientAlert::KINDS))],
            'severity' => ['required', Rule::in(array_keys(PatientAlert::SEVERITY))],
            'description' => ['required', 'string', 'min:2', 'max:200'],
        ], ['description.required' => 'Descreva o alerta (ex.: alergia a penicilina).']);
        /** @var User $user */
        $user = $request->user();
        $this->records->addAlert($patient, $data, $user);

        return back()->with('status', 'Alerta registrado.');
    }

    public function resolve(Request $request, PatientAlert $alert): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->records->resolveAlert($alert, $user);

        return back()->with('status', 'Alerta marcado como resolvido.');
    }
}
