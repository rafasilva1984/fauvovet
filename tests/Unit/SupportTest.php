<?php

namespace Tests\Unit;

use App\Support\BrazilianDocument;
use App\Support\Contrast;
use App\Tenancy\Roles;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function test_permissoes_negadas_por_padrao(): void
    {
        $this->assertFalse(Roles::has(['receptionist'], 'clinical:read'));
        $this->assertTrue(Roles::has(['veterinarian'], 'clinical:read'));
        $this->assertFalse(Roles::has(['veterinarian'], 'theme:update'));
        $this->assertSame([], Roles::permissions(['platform_admin', 'hacker']));
        foreach (['finance', 'stock', 'pet_services'] as $r) {
            $this->assertFalse(Roles::has([$r], 'clinical:read'), $r);
        }
    }

    public function test_painel_por_funcao(): void
    {
        $this->assertSame('management', Roles::dashboard(['tenant_owner']));
        $this->assertSame('clinical', Roles::dashboard(['veterinarian']));
        $this->assertSame('reception', Roles::dashboard(['receptionist']));
        $this->assertSame('finance', Roles::dashboard(['finance']));
        $this->assertSame('operations', Roles::dashboard(['stock']));
    }

    public function test_cpf_cnpj_por_digito_verificador(): void
    {
        $this->assertTrue(BrazilianDocument::isValidCpf('529.982.247-25'));
        $this->assertFalse(BrazilianDocument::isValidCpf('111.111.111-11'));
        $this->assertFalse(BrazilianDocument::isValidCpf('529.982.247-24'));
        $this->assertTrue(BrazilianDocument::isValidCnpj('11.222.333/0001-81'));
        $this->assertFalse(BrazilianDocument::isValidCnpj('11.222.333/0001-80'));
    }

    public function test_contraste_wcag(): void
    {
        $this->assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#ffffff'), 0.01);
        $this->assertTrue(Contrast::passesAa('#0e5e66'));
        $this->assertFalse(Contrast::passesAa('#808080'));
        $this->assertFalse(Contrast::passesAa('red;}body{')); // nunca injeta CSS
    }
}
