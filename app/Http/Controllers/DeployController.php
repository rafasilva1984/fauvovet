<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Operações de deploy em hospedagem SEM SSH (Locaweb por painel/FTP).
 * Protegidas por: DEPLOY_TOOLS_ENABLED=true (desligar após o deploy) + token no cabeçalho X-Deploy-Token
 * (o servidor guarda só o SHA-256) + limite de 5 req/min por IP. Respostas sem segredos.
 */
final class DeployController extends Controller
{
    public function diagnostics(Request $request): JsonResponse
    {
        $this->authorizeDeploy($request);
        $db = ['ok' => false];
        try {
            $db = [
                'ok' => true,
                'version' => DB::scalar('select version()'),
                'pending_migrations' => $this->pendingMigrations(),
            ];
        } catch (Throwable $e) {
            $db['error'] = class_basename($e);
        }
        $required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'sodium', 'dom'];

        return response()->json([
            'php' => PHP_VERSION,
            'extensions_missing' => array_values(array_filter($required, fn ($e) => ! extension_loaded($e))),
            'argon2id' => defined('PASSWORD_ARGON2ID'),
            'hash_driver' => config('hashing.driver'),
            'https' => $request->isSecure(),
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'session_secure_cookie' => (bool) config('session.secure'),
            'storage_writable' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            // PDFs emitidos (documentos clínicos) ficam aqui — fora da área pública.
            // Anexos: o PHP precisa aceitar arquivos do tamanho configurado (ajustado por deploy/public/.user.ini).
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'private_storage_writable' => is_dir(storage_path('app/private')) && is_writable(storage_path('app/private')),
            'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
            'database' => $db,
            'warnings' => $this->warnings($request),
        ]);
    }

    public function migrate(Request $request): JsonResponse
    {
        $this->authorizeDeploy($request);
        Log::channel('deploy')->info('migrate:start');
        $code = Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        Log::channel('deploy')->info('migrate:end', ['exit' => $code]);

        return response()->json(['exit_code' => $code, 'output' => trim($output)], $code === 0 ? 200 : 500);
    }

    public function optimize(Request $request): JsonResponse
    {
        $this->authorizeDeploy($request);
        $results = [];
        foreach (['config:clear', 'route:clear', 'view:clear', 'config:cache', 'route:cache', 'view:cache'] as $cmd) {
            $results[$cmd] = Artisan::call($cmd);
        }
        Log::channel('deploy')->info('optimize', $results);

        return response()->json(['results' => $results], in_array(1, $results, true) ? 500 : 200);
    }

    private function authorizeDeploy(Request $request): void
    {
        $expected = (string) config('fauvovet.deploy.token_sha256');
        $given = (string) $request->header('X-Deploy-Token', '');
        // 404 (e não 401/403) para não revelar a existência das ferramentas quando desligadas/sem token.
        abort_unless(config('fauvovet.deploy.enabled') && strlen($expected) === 64 && $given !== '' && hash_equals($expected, hash('sha256', $given)), 404);
    }

    private function pendingMigrations(): int
    {
        $files = glob(database_path('migrations/*.php')) ?: [];
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];

        return count(array_diff(array_map(fn ($f) => basename($f, '.php'), $files), $ran));
    }

    /** @return list<string> */
    private function warnings(Request $request): array
    {
        $w = [];
        if (config('app.debug')) {
            $w[] = 'APP_DEBUG=true em produção expõe detalhes de erro. Use false.';
        }
        if (! $request->isSecure()) {
            $w[] = 'Sem HTTPS: proibido inserir dados reais de tutores/pacientes até ativar o certificado.';
        }
        if (! config('session.secure')) {
            $w[] = 'SESSION_SECURE_COOKIE=false: ative junto com o HTTPS.';
        }
        if (config('fauvovet.allow_self_signup')) {
            $w[] = 'Cadastro público de clínicas está LIGADO.';
        }

        return $w;
    }
}
