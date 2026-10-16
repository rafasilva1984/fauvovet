{{-- Item compacto da visão de semana: HTML simples (sem componentes) para renderizar centenas de itens rápido. --}}
<a href="{{ route('agenda.show', $a) }}" class="fv-at-{{ strtolower($a->appointment_type_id) }} fv-lift block rounded-xl border border-l-4 border-line bg-surface px-3 py-2 text-sm shadow-card {{ $a->status === 'cancelled' ? 'opacity-70 line-through' : '' }}">
    <span class="font-bold tabular-nums">{{ $a->starts_at->timezone($tz)->format('H:i') }}</span> <span class="font-medium">{{ $a->patient->name }}</span>
    <span class="block truncate text-xs text-muted">{{ $a->type->name }} · {{ \App\Models\Appointment::STATUS[$a->status] }}</span>
</a>
