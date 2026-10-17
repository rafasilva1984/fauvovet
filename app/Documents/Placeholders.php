<?php

namespace App\Documents;

/**
 * Variáveis permitidas nos modelos de documento (whitelist, CLAUDE.md §4.3). Nunca código: só {{grupo.campo}}.
 * Variável fora da lista é recusada ao salvar o modelo, e nunca é avaliada.
 */
final class Placeholders
{
    public const LIST = [
        'clinica.nome' => 'Nome da clínica',
        'clinica.documento' => 'CNPJ/CPF da clínica',
        'unidade.nome' => 'Unidade',
        'unidade.endereco' => 'Endereço da unidade',
        'unidade.cidade' => 'Cidade/UF da unidade',
        'profissional.nome' => 'Nome do profissional',
        'profissional.crmv' => 'CRMV do profissional',
        'responsavel.nome' => 'Nome do responsável',
        'responsavel.documento' => 'CPF/CNPJ do responsável',
        'responsavel.telefone' => 'Telefone do responsável',
        'responsavel.endereco' => 'Endereço do responsável',
        'paciente.nome' => 'Nome do paciente',
        'paciente.especie' => 'Espécie',
        'paciente.raca' => 'Raça',
        'paciente.sexo' => 'Sexo',
        'paciente.idade' => 'Idade',
        'paciente.peso_atual' => 'Peso mais recente',
        'paciente.microchip' => 'Microchip',
        'atendimento.data' => 'Data do atendimento',
        'atendimento.queixa' => 'Queixa principal',
        'atendimento.conduta' => 'Conduta e plano',
        'atendimento.orientacoes' => 'Orientações ao tutor',
        'atendimento.diagnosticos' => 'Diagnósticos confirmados',
        'data.hoje' => 'Data de hoje (dd/mm/aaaa)',
        'data.extenso' => 'Data de hoje por extenso',
    ];

    private const PATTERN = '/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/u';

    /** @return list<string> variáveis usadas que não existem na lista */
    public static function unknown(string $body): array
    {
        // Qualquer {{...}} fora da lista (inclusive malformado, ex.: "{{ paciente }}") é recusado.
        preg_match_all('/\{\{(.*?)\}\}/u', $body, $all);

        return array_values(array_unique(array_filter(array_map('trim', $all[1]), fn ($k) => ! array_key_exists($k, self::LIST))));
    }

    /**
     * Substitui as variáveis conhecidas pelos valores. Texto puro: o escape para HTML acontece só na geração do PDF.
     *
     * @param  array<string, string>  $values
     */
    public static function fill(string $body, array $values): string
    {
        return (string) preg_replace_callback(self::PATTERN, fn ($m) => $values[$m[1]] ?? '', $body);
    }
}
