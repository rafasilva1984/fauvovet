{{-- Cor de cada tipo de atendimento como classe (CSP sem estilos inline). Cores validadas como #RRGGBB. --}}
<style nonce="{{ Vite::cspNonce() }}">
    @foreach ($types as $t)
        @if (preg_match('/^#[0-9a-fA-F]{6}$/', $t->color))
            .fv-at-{{ strtolower($t->id) }} { border-left-color: {{ $t->color }}; }
            .fv-at-dot-{{ strtolower($t->id) }} { background-color: {{ $t->color }}; }
        @endif
    @endforeach
</style>
