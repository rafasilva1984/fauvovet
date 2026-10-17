<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder as Seed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** CEP → endereço (consulta pelo servidor, ViaCEP simulado) e UF por lista. */
final class AddressTest extends TestCase
{
    private const RAFAEL = '01K7FV0000000000000GRDA002';

    public function test_cep_preenche_endereco_e_fica_em_cache(): void
    {
        Http::fake(['viacep.com.br/ws/01001000/json/' => Http::response([
            'cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP',
        ])]);
        $this->loginInto('carla', Seed::TENANT_A);
        $this->getJson('/cep/01001000')->assertOk()->assertExactJson([
            'postal_code' => '01001000', 'street' => 'Praça da Sé', 'district' => 'Sé', 'city' => 'São Paulo', 'state' => 'SP',
        ]);
        $this->getJson('/cep/01001000')->assertOk();
        Http::assertSentCount(1); // segunda consulta veio do cache
    }

    public function test_cep_inexistente_ou_servico_fora_do_ar_responde_404_sem_cache(): void
    {
        $attempts = 0;
        Http::fake([
            'viacep.com.br/ws/99999999/json/' => Http::response(['erro' => true]),
            'viacep.com.br/ws/12345678/json/' => function () use (&$attempts) {
                $attempts++;
                throw new ConnectionException('timeout');
            },
        ]);
        $this->loginInto('carla', Seed::TENANT_A);
        $this->getJson('/cep/99999999')->assertNotFound()->assertJsonPath('message', 'CEP não encontrado. Preencha o endereço manualmente.');
        $this->getJson('/cep/12345678')->assertNotFound();
        $this->getJson('/cep/12345678')->assertNotFound();
        $this->assertSame(2, $attempts); // falha de conexão não fica em cache: tenta de novo
        $this->get('/cep/123')->assertNotFound(); // formato inválido nem chega ao serviço
        $this->assertSame(2, $attempts);
    }

    public function test_cep_exige_login(): void
    {
        Http::fake();
        $this->get('/cep/01001000')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_endereco_completo_e_uf_da_lista(): void
    {
        $this->loginInto('carla', Seed::TENANT_A);
        $base = ['name' => 'Rafael Lima (fictício)', 'phone' => '11912345678', 'status' => 'active'];
        $this->put('/responsaveis/'.self::RAFAEL, [...$base, 'state' => 'XX'])->assertSessionHasErrors('state');
        $this->put('/responsaveis/'.self::RAFAEL, [...$base, 'postal_code' => '01001-000', 'address_line' => 'Praça da Sé', 'address_number' => '100',
            'address_complement' => 'Sala 2', 'district' => 'Sé', 'city' => 'São Paulo', 'state' => 'SP'])->assertSessionHasNoErrors();
        $g = app(TenantContext::class)->runAsSystem(fn () => Guardian::query()->find(self::RAFAEL));
        $this->assertSame(['01001000', '100', 'Sala 2', 'Sé', 'SP'], [$g?->postal_code, $g?->address_number, $g?->address_complement, $g?->district, $g?->state]);
        $this->get('/responsaveis/'.self::RAFAEL)->assertSee('Praça da Sé, 100 — Sala 2')->assertSee('CEP 01001-000');
        $this->get('/responsaveis/'.self::RAFAEL.'/editar')->assertSee('SP — São Paulo')->assertSee('data-url', false);
    }
}
