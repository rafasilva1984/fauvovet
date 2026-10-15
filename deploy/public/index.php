<?php

/**
 * Front controller de PRODUÇÃO (hospedagem por FTP, sem SSH).
 * Fica em public_html/fauvovet/index.php; o código da aplicação fica FORA da área pública, em
 * app-fauvovet/ (irmã de public_html/ na raiz do FTP). Ver docs/DEPLOY_LOCAWEB.md.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appPath = getenv('FAUVOVET_APP_PATH') ?: dirname(__DIR__, 2).'/app-fauvovet';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// Assets compilados (build/) e manifesto do Vite ficam aqui, na pasta pública.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
