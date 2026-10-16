<?php

namespace App\Providers;

use App\Support\LayoutData;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantQueryFirewall;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uma instância por requisição/comando (scoped), nunca compartilhada entre requisições.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(TenantQueryFirewall::class);
        $this->app->scoped(LayoutData::class);
    }

    public function boot(): void
    {
        // Firewall de SQL multi-tenant: roda ANTES de cada consulta.
        DB::beforeExecuting(function (string $query, array $bindings): void {
            app(TenantQueryFirewall::class)->inspect($query, $bindings);
        });

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinutes(15, 30)->by('ip:'.$r->ip()),
            Limit::perMinutes(15, 10)->by('email:'.mb_strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('signup', fn (Request $r) => Limit::perHour(10)->by('ip:'.$r->ip()));
        RateLimiter::for('invitation', fn (Request $r) => Limit::perMinute(30)->by('ip:'.$r->ip()));
        RateLimiter::for('invite-create', fn (Request $r) => Limit::perHour(60)->by('user:'.$r->user()?->getAuthIdentifier()));

        // Dados das telas autenticadas (clínica ativa, tema, permissões) — calculados 1x por requisição.
        View::composer(['layouts.app', 'dashboard', 'units.*', 'team.*', 'settings.*'], function ($view): void {
            if (app(TenantContext::class)->id() !== null) {
                $view->with(app(LayoutData::class)->toArray());
            }
        });
    }
}
