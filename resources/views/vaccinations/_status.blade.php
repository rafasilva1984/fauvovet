@php
    $map = [
        'overdue' => ['danger', 'Atrasada'.($days !== null ? ' há '.abs($days).' '.(abs($days) === 1 ? 'dia' : 'dias') : '')],
        'soon' => ['warning', $days === 0 ? 'Vence hoje' : 'Vence em '.$days.' '.($days === 1 ? 'dia' : 'dias')],
        'ok' => ['success', 'Em dia'],
        'complete' => ['neutral', 'Sem próxima dose'],
    ];
    [$tone, $label] = $map[$status];
@endphp
<x-badge :tone="$tone" :dot="true">{{ $label }}</x-badge>
