<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DeployAndHeadersTest extends TestCase
{
    private const TOKEN = 'token-de-teste-ficticio-com-tamanho-suficiente';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('file')->flush(); // limitador de deploy guarda contadores em arquivo
    }

    public function test_ferramentas_de_deploy_desligadas_respondem_404(): void
    {
        $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => self::TOKEN])->assertNotFound();
    }

    public function test_token_errado_ou_ausente_responde_404(): void
    {
        config(['fauvovet.deploy.enabled' => true, 'fauvovet.deploy.token_sha256' => hash('sha256', self::TOKEN)]);
        $this->getJson('/_deploy/diagnostico')->assertNotFound();
        $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => 'errado'])->assertNotFound();
        $this->postJson('/_deploy/migrar', [], ['X-Deploy-Token' => 'errado'])->assertNotFound();
    }

    public function test_diagnostico_com_token_nao_expoe_segredos(): void
    {
        config(['fauvovet.deploy.enabled' => true, 'fauvovet.deploy.token_sha256' => hash('sha256', self::TOKEN)]);
        $res = $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => self::TOKEN])->assertOk()
            ->assertJsonPath('database.ok', true)->assertJsonPath('argon2id', true);
        $body = $res->getContent();
        foreach ([(string) config('database.connections.mysql.password'), (string) config('app.key'), self::TOKEN] as $secret) {
            if ($secret !== '') {
                $this->assertStringNotContainsString($secret, $body);
            }
        }
    }

    /** Antes da 1ª migração não há tabelas cache/sessions: o endpoint não pode depender do banco. */
    public function test_diagnostico_responde_mesmo_sem_banco_utilizavel(): void
    {
        config(['fauvovet.deploy.enabled' => true, 'fauvovet.deploy.token_sha256' => hash('sha256', self::TOKEN)]);
        $original = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => 'inexistente_test']);
        DB::purge('mysql');
        try {
            $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => self::TOKEN])->assertOk()->assertJsonPath('database.ok', false);
        } finally {
            config(['database.connections.mysql.database' => $original]);
            DB::purge('mysql');
        }
    }

    public function test_deploy_tem_limite_de_tentativas(): void
    {
        config(['fauvovet.deploy.enabled' => true, 'fauvovet.deploy.token_sha256' => hash('sha256', self::TOKEN)]);
        Cache::store('file')->flush();
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => 'errado']);
        }
        $this->getJson('/_deploy/diagnostico', ['X-Deploy-Token' => self::TOKEN])->assertStatus(429);
        Cache::store('file')->flush();
    }

    public function test_cabecalhos_de_seguranca_e_csp_com_nonce(): void
    {
        $res = $this->get('/login')->assertOk();
        $csp = (string) $res->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotNull($res->headers->get('X-Request-Id'));
    }

    public function test_paginas_autenticadas_nao_ficam_em_cache(): void
    {
        $this->login('diana');
        $this->assertStringContainsString('no-store', (string) $this->get('/painel')->headers->get('Cache-Control'));
    }

    public function test_pagina_de_erro_em_portugues(): void
    {
        $this->get('/nao-existe')->assertNotFound()->assertSee('Página não encontrada');
    }

    public function test_seed_tem_duas_clinicas(): void
    {
        $this->assertNotSame(Seed::TENANT_A, Seed::TENANT_B);
    }
}
