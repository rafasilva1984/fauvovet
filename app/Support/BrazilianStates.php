<?php

namespace App\Support;

/** Unidades federativas (UF) para listas de seleção e validação. */
final class BrazilianStates
{
    public const LIST = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará',
        'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
        'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    /** @return array<string, string> "SP" => "SP — São Paulo" */
    public static function options(): array
    {
        $options = [];
        foreach (self::LIST as $code => $name) {
            $options[$code] = "{$code} — {$name}";
        }

        return $options;
    }
}
