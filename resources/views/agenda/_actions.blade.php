{{-- Ações rápidas de status. $quick = só as do fluxo principal (lista); senão todas (detalhe). --}}
@php
    $next = \App\Models\Appointment::TRANSITIONS[$a->status] ?? [];
    if ($quick ?? false) {
        $next = array_values(array_intersect($next, ['confirmed', 'waiting', 'in_progress', 'completed']));
    }
    $next = array_values(array_filter($next, fn ($to) => ! in_array($to, \App\Models\Appointment::ATTEND, true) || $fvCan('schedule:attend')));
@endphp
@if ($fvCan('schedule:write') && $next)
    <div class="flex flex-wrap gap-2">
        @foreach ($next as $to)
            @if ($to === 'cancelled')
                <form method="POST" action="{{ route('agenda.transition', $a) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="to" value="cancelled">
                    <input type="hidden" name="back" value="{{ $back ?? 'show' }}">
                    <label for="motivo-{{ $a->id }}" class="sr-only">Motivo do cancelamento de {{ $a->patient->name }}</label>
                    <input id="motivo-{{ $a->id }}" name="reason" maxlength="200" placeholder="Motivo" class="min-h-11 rounded-xl border border-line bg-surface px-3 text-sm">
                    <x-button variant="secondary">Cancelar<span class="sr-only"> agendamento de {{ $a->patient->name }}</span></x-button>
                </form>
            @else
                <form method="POST" action="{{ route('agenda.transition', $a) }}">
                    @csrf
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="back" value="{{ $back ?? 'show' }}">
                    <x-button :variant="in_array($to, ['waiting', 'in_progress', 'completed'], true) ? 'primary' : 'secondary'" class="min-h-11 text-sm">
                        {{ \App\Models\Appointment::ACTION_LABELS[$to] }}<span class="sr-only"> — {{ $a->patient->name }}</span>
                    </x-button>
                </form>
            @endif
        @endforeach
    </div>
@endif
