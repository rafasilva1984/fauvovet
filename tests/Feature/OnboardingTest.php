<?php

namespace Tests\Feature;

use Tests\TestCase;

final class OnboardingTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'person_type' => 'PF',
            'trade_name' => 'Dra. Exemplo Exóticos (fictícia)',
            'owner_name' => 'Dra. Exemplo (fictícia)',
            'email' => 'onb-'.uniqid().'@exemplo.test',
            'password' => 'senha-forte-de-teste-1',
            'password_confirmation' => 'senha-forte-de-teste-1',
            'unit_name' => 'Atendimento domiciliar',
            'timezone' => 'America/Manaus',
        ], $override);
    }

    public function test_cria_clinica_unidade_e_titular_e_entra_no_painel(): void
    {
        $this->post('/cadastro', $this->payload())->assertRedirect('/painel');
        $this->get('/unidades')->assertOk()->assertSee('Atendimento domiciliar')->assertSee('America/Manaus')->assertDontSee('Aurora');
        $this->get('/equipe')->assertOk()->assertSee('Titular');
    }

    public function test_email_duplicado_tem_mensagem_generica(): void
    {
        $this->from('/cadastro')->post('/cadastro', $this->payload(['email' => $this->userEmail('ana')]))
            ->assertSessionHasErrors(['email' => 'Não foi possível concluir o cadastro com este e-mail. Se você já possui conta, faça login.']);
    }

    public function test_valida_cpf_senha_e_confirmacao(): void
    {
        $this->post('/cadastro', $this->payload(['document' => '123.456.789-00']))->assertSessionHasErrors(['document' => 'CPF inválido.']);
        $this->post('/cadastro', $this->payload(['password' => 'curta', 'password_confirmation' => 'curta']))->assertSessionHasErrors('password');
        $this->post('/cadastro', $this->payload(['password_confirmation' => 'outra-senha-diferente']))->assertSessionHasErrors('password');
    }

    public function test_cadastro_publico_desligado_responde_404(): void
    {
        config(['fauvovet.allow_self_signup' => false]);
        $this->get('/cadastro')->assertNotFound();
        $this->post('/cadastro', $this->payload())->assertNotFound();
    }
}
