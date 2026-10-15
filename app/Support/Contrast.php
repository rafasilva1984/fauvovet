<?php

namespace App\Support;

use InvalidArgumentException;

/** Contraste WCAG 2.x para validar o tema de cada clínica (CLAUDE.md §4.1). */
final class Contrast
{
    public const HEX = '/^#[0-9a-fA-F]{6}$/';

    public const AA_NORMAL = 4.5;

    public static function luminance(string $hex): float
    {
        if (! preg_match(self::HEX, $hex)) {
            throw new InvalidArgumentException("Cor inválida: {$hex}");
        }
        $n = hexdec(substr($hex, 1));
        $channel = static function (int $c): float {
            $s = $c / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(($n >> 16) & 0xFF) + 0.7152 * $channel(($n >> 8) & 0xFF) + 0.0722 * $channel($n & 0xFF);
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Cor de texto (branco ou quase-preto) com mais contraste sobre o fundo. */
    public static function textOn(string $background): string
    {
        return self::ratio($background, '#ffffff') >= self::ratio($background, '#0f1e24') ? '#ffffff' : '#0f1e24';
    }

    public static function passesAa(string $background): bool
    {
        return preg_match(self::HEX, $background) === 1 && self::ratio($background, self::textOn($background)) >= self::AA_NORMAL;
    }
}
