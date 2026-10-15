<?php

namespace Tests;

use App\Models\Membership;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Migra do zero e aplica o seed fictício (2 clínicas) uma vez; cada teste roda em transação. */
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public const PASSWORD = 'senha-de-teste-ficticia';

    protected function setUp(): void
    {
        parent::setUp();
        $db = (string) config('database.connections.mysql.database');
        $host = (string) config('database.connections.mysql.host');
        // Guarda: testes NUNCA rodam fora de banco local *_test (protege dev/produção).
        if (! str_ends_with($db, '_test') || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException("Testes recusados: banco \"{$db}\" em \"{$host}\" não é local *_test.");
        }
    }

    protected function beforeRefreshingDatabase(): void
    {
        $db = (string) config('database.connections.mysql.database');
        if (! str_ends_with($db, '_test')) {
            throw new RuntimeException("migrate:fresh recusado no banco \"{$db}\".");
        }
    }

    protected function userEmail(string $key): string
    {
        return DatabaseSeeder::USERS[$key]['email'];
    }

    protected function login(string $key, ?string $password = null): TestResponse
    {
        return $this->post('/login', ['email' => $this->userEmail($key), 'password' => $password ?? self::PASSWORD]);
    }

    /** Login + escolha explícita do vínculo da clínica indicada (primeira unidade). */
    protected function loginInto(string $key, string $tenantId, ?string $unitId = null): void
    {
        $this->login($key)->assertRedirect();
        $membershipId = $this->membershipId($key, $tenantId);
        $this->post('/contexto', ['membership_id' => $membershipId, 'unit_id' => $unitId])->assertRedirect('/painel');
    }

    protected function membershipId(string $key, string $tenantId): string
    {
        return (string) app(TenantContext::class)->runAsSystem(fn () => Membership::query()
            ->where('tenant_id', $tenantId)->where('user_id', DatabaseSeeder::USERS[$key]['id'])->value('id'));
    }
}
