<?php

/**
 * Roteador do servidor embutido do PHP que imita a hospedagem: a aplicação responde em /fauvovet
 * (subdiretório), como em public_html/fauvovet na Locaweb. Uso (via scripts/dev.mjs ou e2e):
 *   php -S 127.0.0.1:8000 -t .data/devroot scripts/dev-router.php
 * Somente para desenvolvimento/testes locais.
 */
$base = getenv('FAUVOVET_BASE_PATH') ?: '/fauvovet';
$public = realpath(getenv('FAUVOVET_PUBLIC_DIR') ?: __DIR__.'/../public');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '') {
    header('Location: '.$base.'/', true, 302);

    return true;
}
if ($path !== $base && ! str_starts_with($path, $base.'/')) {
    http_response_code(404);
    echo 'Fora do subdiretório '.$base;

    return true;
}

$relative = substr($path, strlen($base));
$file = $public.$relative;
// Arquivos estáticos reais de public/ (build/, favicon...), sem permitir sair da pasta.
if ($relative !== '' && $relative !== '/' && is_file($file) && str_starts_with(realpath($file) ?: '', $public) && ! str_ends_with($file, '.php')) {
    return false; // servido pelo servidor embutido a partir do docroot (.data/devroot/fauvovet → public)
}

$_SERVER['SCRIPT_NAME'] = $base.'/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';
$_SERVER['PHP_SELF'] = $base.'/index.php';
chdir($public);
require $public.'/index.php';
