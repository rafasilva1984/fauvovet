<?php

namespace Tests\Unit;

use App\Clinical\DoseCalculator;
use PHPUnit\Framework\TestCase;

/** Conta de dose por peso: arredondamento à fração possível e dose efetiva depois do arredondamento. */
final class DoseCalculatorTest extends TestCase
{
    public function test_comprimido_arredonda_para_quarto_e_mostra_dose_efetiva(): void
    {
        // 12,5 mg/kg × 28,15 kg = 351,875 mg; ÷ 250 mg = 1,4075 → 1 ½ comprimido = 375 mg (13,32 mg/kg).
        $c = DoseCalculator::calculate('tablet', 250.0, 12.5, 28.15);
        $this->assertNotNull($c);
        $this->assertEqualsWithDelta(351.875, $c['target_mg'], 1e-6);
        $this->assertSame(1.5, $c['quantity']);
        $this->assertEqualsWithDelta(375.0, $c['dose_mg'], 1e-6);
        $this->assertEqualsWithDelta(13.32, $c['effective_mg_kg'], 0.01);
        $this->assertSame('1 ½ comprimido (375 mg)', $c['text']);
        $this->assertFalse($c['too_small']);
    }

    public function test_liquido_em_ml_e_plural_de_comprimidos(): void
    {
        $this->assertSame('2,4 mL (24 mg)', DoseCalculator::calculate('suspension', 10.0, 2.0, 12.0)['text'] ?? null);
        $this->assertSame('2 ¼ comprimidos (112,5 mg)', DoseCalculator::calculate('tablet', 50.0, 5.0, 22.0)['text'] ?? null);
        $this->assertSame('0,35 mL (17,5 mg)', DoseCalculator::calculate('injectable', 50.0, 0.5, 35.0)['text'] ?? null);
    }

    public function test_dose_menor_que_a_menor_fracao_e_sinalizada(): void
    {
        $c = DoseCalculator::calculate('tablet', 500.0, 1.0, 0.09); // calopsita: 0,09 mg em comprimido de 500 mg
        $this->assertTrue($c['too_small'] ?? false);
        $this->assertSame('', $c['text'] ?? null);
    }

    public function test_sem_concentracao_ou_forma_calculavel_devolve_so_mg(): void
    {
        $this->assertSame('140 mg', DoseCalculator::calculate('topical', null, 5.0, 28.0)['text'] ?? null);
        $this->assertSame('0,45 mg', DoseCalculator::calculate(null, null, 0.5, 0.9)['text'] ?? null);
        $this->assertNull(DoseCalculator::calculate('tablet', 250.0, null, 10.0));
        $this->assertNull(DoseCalculator::calculate('tablet', 250.0, 10.0, null));
        $this->assertNull(DoseCalculator::calculate('tablet', 250.0, -1.0, 10.0));
    }

    public function test_fracoes(): void
    {
        $this->assertSame(['¼', '½', '¾', '1', '1 ¼', '3'], array_map([DoseCalculator::class, 'fraction'], [0.25, 0.5, 0.75, 1.0, 1.25, 3.0]));
    }
}
