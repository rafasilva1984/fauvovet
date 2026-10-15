<?php

namespace App\Support;

/** Validação de CPF/CNPJ por dígitos verificadores (não consulta a Receita Federal). */
final class BrazilianDocument
{
    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isValidCpf(string $value): bool
    {
        $cpf = self::digits($value);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        foreach ([9, 10] as $len) {
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $cpf[$i] * ($len + 1 - $i);
            }
            if ((($sum * 10) % 11) % 10 !== (int) $cpf[$len]) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $value): bool
    {
        $cnpj = self::digits($value);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        foreach ([$w1, [6, ...$w1]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $w) {
                $sum += $w * (int) $cnpj[$i];
            }
            $rest = $sum % 11;
            if (($rest < 2 ? 0 : 11 - $rest) !== (int) $cnpj[count($weights)]) {
                return false;
            }
        }

        return true;
    }
}
