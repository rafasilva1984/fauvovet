<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Patient;
use App\Models\Unit;
use App\Services\ScheduleService;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
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
            'today' => $context->can('schedule:read') ? $this->today($context) : null,
            'patientCount' => $context->can('patients:read') ? Patient::query()->where('status', 'active')->count() : null,
            'memberCount' => $context->can('members:read') ? Membership::query()->where('status', 'active')->count() : null,
            'activity' => $canAudit ? AuditEvent::query()->with('actor:id,name')->latest('id')->limit(8)->get() : null,
        ]);
    }

    /**
     * Agenda de hoje na unidade ativa (ou em todas as unidades permitidas), no fuso de cada unidade.
     *
     * @return array{total: int, waiting: int, next: Collection<int, Appointment>}
     */
    private function today(TenantContext $context): array
    {
        $units = app(ScheduleService::class)->allowedUnits();
        $query = Appointment::query()->whereNotIn('status', ['cancelled'])
            ->where(function ($w) use ($units) {
                foreach ($units as $u) {
                    $start = now($u->timezone)->startOfDay();
                    $w->orWhere(fn ($q) => $q->where('unit_id', $u->id)
                        ->where('starts_at', '>=', $start->copy()->utc())->where('starts_at', '<', $start->copy()->addDay()->utc()));
                }
            });
        if ($units->isEmpty()) {
            $query->whereRaw('1 = 0');
        }

        return [
            'total' => (clone $query)->count(),
            'waiting' => (clone $query)->where('status', 'waiting')->count(),
            'next' => (clone $query)->whereIn('status', ['scheduled', 'confirmed', 'waiting'])->where('ends_at', '>=', now())
                ->with(['patient:id,name', 'type:id,name', 'unit:id,name,timezone'])->orderBy('starts_at')->limit(5)->get(),
        ];
    }
}
