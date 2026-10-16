{{-- Item compacto da visão de semana: HTML simples (sem componentes) para renderizar centenas de itens rápido. --}}
<a href="{{ route('agenda.show', $a) }}" class="fv-at-{{ strtolower($a->appointment_type_id) }} block rounded-xl border border-l-4 border-line bg-surface px-3 py-2 text-sm hover:bg-surface-2 {{ $a->status === 'cancelled' ? 'opacity-70 line-through' : '' }}">
    <span class="font-semibold tabular-nums">{{ $a->starts_at->timezone($tz)->format('H:i') }}</span> {{ $a->patient->name }}
    <span class="block truncate text-xs text-muted">{{ $a->type->name }} · {{ \App\Models\Appointment::STATUS[$a->status] }}</span>
</a>
