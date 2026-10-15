<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite de 5 requisições/minuto por IP nas ferramentas de deploy, guardado em ARQUIVO:
 * precisa funcionar antes da primeira migração, quando as tabelas cache/sessions ainda não existem.
 */
final class DeployRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $limiter = new RateLimiter(Cache::store('file'));
        $key = 'deploy:'.sha1((string) $request->ip());
        abort_if($limiter->tooManyAttempts($key, 5), 429, 'Muitas tentativas.');
        $limiter->hit($key, 60);

        return $next($request);
    }
}
