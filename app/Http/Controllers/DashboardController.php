<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Unit;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        $canAudit = $context->can('audit:read');

        return view('dashboard', [
            'kind' => Roles::dashboard($context->roles()),
            'unitCount' => Unit::query()->count(),
            'patientCount' => $context->can('patients:read') ? Patient::query()->where('status', 'active')->count() : null,
            'memberCount' => $context->can('members:read') ? Membership::query()->where('status', 'active')->count() : null,
            'activity' => $canAudit ? AuditEvent::query()->with('actor:id,name')->latest('id')->limit(8)->get() : null,
        ]);
    }
}
