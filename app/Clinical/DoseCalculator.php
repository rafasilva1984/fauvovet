<?php

namespace App\Clinical;

use App\Models\Medication;

/**
 * Conta de dose por peso (aritmética, não decisão clínica): mg/kg × kg → mg; mg ÷ concentração → quantidade,
 * arredondada à fração possível da forma (¼ de comprimido, 0,1 mL...). Devolve também a dose EFETIVA depois do
 * arredondamento, para o veterinário conferir o que de fato será dado.
 */
final class DoseCalculator
{
    /**
     * @return array{target_mg: float, quantity: ?float, dose_mg: float, effective_mg_kg: float, text: string, too_small: bool}|null
     */
    public static function calculate(?string $form, ?float $concentrationMg, ?float $doseMgKg, ?float $weightKg): ?array
    {
        if ($doseMgKg === null || $weightKg === null || $doseMgKg <= 0 || $weightKg <= 0) {
            return null;
        }
        $target = $doseMgKg * $weightKg;
        $unit = Medication::UNITS[(string) $form] ?? null;
        if ($unit === null || $concentrationMg === null || $concentrationMg <= 0) {
            return ['target_mg' => $target, 'quantity' => null, 'dose_mg' => $target, 'effective_mg_kg' => $doseMgKg,
                'text' => self::mg($target), 'too_small' => false];
        }
        [$singular, $plural, $step] = $unit;
        $quantity = round(round($target / $concentrationMg / $step) * $step, 4);
        $dose = $quantity * $concentrationMg;
        $label = $form === 'tablet' ? self::fraction($quantity) : Medication::num($quantity, 2);

        return [
            'target_mg' => $target,
            'quantity' => $quantity,
            'dose_mg' => $dose,
            'effective_mg_kg' => $dose / $weightKg,
            'text' => $quantity > 0 ? $label.' '.($quantity >= 2 && $form !== null && in_array($form, ['tablet', 'capsule'], true) ? $plural : $singular).' ('.self::mg($dose).')' : '',
            'too_small' => $quantity <= 0,
        ];
    }

    public static function mg(float $mg): string
    {
        return Medication::num($mg, $mg < 1 ? 3 : 2).' mg';
    }

    /** 1,25 → "1 ¼"; 0,5 → "½"; 3 → "3". */
    public static function fraction(float $q): string
    {
        $whole = (int) floor($q + 1e-9);
        $part = ['', '¼', '½', '¾'][(int) round(($q - $whole) * 4) % 4];

        return trim(($whole > 0 || $part === '' ? (string) $whole : '').($whole > 0 && $part !== '' ? ' ' : '').$part);
    }
}
