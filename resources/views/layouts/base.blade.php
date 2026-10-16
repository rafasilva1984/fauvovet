@php
    use App\Support\Contrast;
    $t = $fvTheme ?? \App\Models\TenantTheme::DEFAULT;
    $primary = preg_match(Contrast::HEX, $t['primary']) ? $t['primary'] : \App\Models\TenantTheme::DEFAULT['primary'];
    $secondary = preg_match(Contrast::HEX, $t['secondary']) ? $t['secondary'] : \App\Models\TenantTheme::DEFAULT['secondary'];
    $mode = in_array($t['mode'], ['light', 'dark'], true) ? $t['mode'] : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR" @if ($mode) data-theme="{{ $mode }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>@hasSection('title')@yield('title') · @endif FauvoVet</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Tokens da clínica DEPOIS do CSS base (senão o padrão sobrescreve). Valores validados como #RRGGBB. --}}
    <style nonce="{{ Vite::cspNonce() }}">
        :root {
            --fv-primary: {{ $primary }};
            --fv-on-primary: {{ Contrast::textOn($primary) }};
            --fv-secondary: {{ $secondary }};
            --fv-on-secondary: {{ Contrast::textOn($secondary) }};
        }
    </style>
    {{-- Estilos por tela (ex.: cores dos tipos de atendimento), sempre com o nonce e valores validados. --}}
    @stack('styles')
</head>
<body class="min-h-dvh">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-card">Pular para o conteúdo</a>
    @yield('body')
</body>
</html>
