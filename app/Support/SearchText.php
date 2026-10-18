<?php

namespace App\Support;

/**
 * Texto para a busca clínica (ADR-017), sem depender de extensão intl nem do FULLTEXT do MySQL.
 *  - normalize(): minúsculas, sem acento e pontuação vira espaço, preservando o COMPRIMENTO caractere a caractere
 *    (posição no texto normalizado = posição no original), o que permite destacar o trecho original;
 *  - stem(): reduz plural comum do português ("dermatites" → "dermatite", "renais" → "renal", "lesões" → "lesao"),
 *    aplicado igual no índice e na consulta;
 *  - parse(): consulta com frases "entre aspas" (retas ou “curvas”), OU/ou entre alternativas e NÃO/-termo para excluir.
 */
final class SearchText
{
    public const MIN_TERM = 3;

    public const MAX_TERM = 40;

    private const STOPWORDS = ['de', 'da', 'do', 'das', 'dos', 'e', 'a', 'o', 'as', 'os', 'em', 'no', 'na', 'nos', 'nas', 'com',
        'por', 'para', 'pra', 'um', 'uma', 'uns', 'umas', 'que', 'se', 'ao', 'aos', 'ou', 'mas', 'sem', 'sob', 'sobre', 'entre',
        'ate', 'apos', 'pelo', 'pela', 'pelos', 'pelas', 'num', 'numa', 'seu', 'sua', 'seus', 'suas', 'ele', 'ela', 'eles', 'elas',
        'foi', 'ser', 'esta', 'este', 'isso', 'esse', 'essa', 'ha', 'mais', 'muito', 'tem', 'como', 'qual', 'quando'];

    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ];

    /** Minúsculas, sem acento, só [a-z0-9] e espaço; mesmo número de caracteres do original. */
    public static function normalize(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            $low = mb_strtolower($ch);
            $low = mb_strlen($low) === 1 ? $low : $ch;
            $low = self::ACCENTS[$low] ?? $low;
            $out .= preg_match('/^[a-z0-9]$/', $low) ? $low : ' ';
        }

        return $out;
    }

    /** Plural comum do português → forma reduzida (mesma regra no índice e na consulta). */
    public static function stem(string $word): string
    {
        if (strlen($word) >= 4 && preg_match('/[ao]es$/', $word)) {
            return substr($word, 0, -3).'ao'; // cães → cão, lesões → lesão, pães → pão
        }
        if (strlen($word) <= 4 || ctype_digit($word)) {
            return $word;
        }
        foreach (['eis' => 'el', 'ais' => 'al', 'ois' => 'ol', 'res' => 'r', 'zes' => 'z', 'ns' => 'm'] as $suffix => $to) {
            if (str_ends_with($word, $suffix)) {
                return substr($word, 0, -strlen($suffix)).$to;
            }
        }
        if (str_ends_with($word, 's') && ! preg_match('/(ss|us|is)$/', $word)) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Termos distintos para indexar (sem stopwords, no mínimo 2 letras, reduzidos e cortados no tamanho da coluna).
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $set = [];
        foreach (preg_split('/\s+/', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            if (strlen($w) < 2 || in_array($w, self::STOPWORDS, true)) {
                continue;
            }
            $set[substr(self::stem($w), 0, self::MAX_TERM)] = true;
        }

        return array_map('strval', array_keys($set));
    }

    /**
     * Interpreta a consulta.
     *  - Alternativas separadas por OU/ou/OR; em cada uma, todos os termos e frases são exigidos (E).
     *  - "frase entre aspas" = sequência exata (sem acento/maiúsculas).
     *  - NÃO termo, NAO termo, NOT termo ou -termo = excluir. Vale para a consulta inteira.
     *  - Termos com menos de 3 letras e palavras comuns ("de", "com"...) são ignorados fora de frases.
     *
     * @return array{groups: list<array{terms: list<string>, phrases: list<string>}>, exclude: list<string>, ignored: list<string>}
     */
    public static function parse(string $query): array
    {
        // Aspas tipográficas (celular, Word, texto copiado) valem como aspas retas.
        $query = str_replace(['“', '”', '„', '‟', '«', '»', '″'], '"', mb_substr(trim($query), 0, 200));
        preg_match_all('/(-?)"([^"]*)"|(\S+)/u', $query, $m, PREG_SET_ORDER);
        $groups = [['terms' => [], 'phrases' => []]];
        $exclude = $ignored = [];
        $negateNext = false;
        foreach ($m as $part) {
            $isPhrase = isset($part[2]) && $part[2] !== '' && ($part[3] ?? '') === '';
            $raw = $isPhrase ? $part[2] : ($part[3] ?? '');
            // "ou" minúsculo também separa alternativas: como palavra de busca ele não teria valor (é comum demais).
            if (! $isPhrase && in_array(mb_strtolower($raw), ['ou', 'or', '|'], true)) {
                $groups[] = ['terms' => [], 'phrases' => []];

                continue;
            }
            if (! $isPhrase && in_array($raw, ['NÃO', 'NAO', 'NOT'], true)) {
                $negateNext = true;

                continue;
            }
            if (! $isPhrase && in_array($raw, ['E', 'AND'], true)) {
                continue;
            }
            $negate = $negateNext || $part[1] === '-' || (! $isPhrase && str_starts_with($raw, '-'));
            $negateNext = false;
            $raw = $isPhrase ? $raw : ltrim($raw, '-');
            $norm = trim((string) preg_replace('/\s+/', ' ', self::normalize($raw)));
            if ($norm === '') {
                continue;
            }
            if ($isPhrase && str_contains($norm, ' ')) {
                if ($negate) {
                    $exclude[] = '"'.$norm.'"';
                } else {
                    $groups[array_key_last($groups)]['phrases'][] = $norm;
                }

                continue;
            }
            foreach (explode(' ', $norm) as $w) {
                if (strlen($w) < self::MIN_TERM || in_array($w, self::STOPWORDS, true)) {
                    $ignored[] = $w;

                    continue;
                }
                $stem = substr(self::stem($w), 0, self::MAX_TERM);
                if ($negate) {
                    $exclude[] = $stem;
                } else {
                    $groups[array_key_last($groups)]['terms'][] = $stem;
                }
            }
        }
        $groups = array_values(array_filter(
            array_map(fn ($g) => ['terms' => array_values(array_unique($g['terms'])), 'phrases' => array_values(array_unique($g['phrases']))], $groups),
            fn ($g) => $g['terms'] !== [] || $g['phrases'] !== [],
        ));

        return ['groups' => $groups, 'exclude' => array_values(array_unique($exclude)), 'ignored' => array_values(array_unique($ignored))];
    }

    /** Palavra que vira termo de consulta (fora de aspas): 3+ letras e não é palavra comum. */
    public static function significant(string $word): bool
    {
        return strlen($word) >= self::MIN_TERM && ! in_array($word, self::STOPWORDS, true);
    }

    /**
     * Variantes de um grupo de sinônimos (uma expressão por linha ou separadas por vírgula/ponto e vírgula):
     * palavra única → "t:radical"; expressão → "p:frase normalizada". Palavras comuns sozinhas são ignoradas.
     *
     * @return list<string>
     */
    public static function synonymVariants(string $terms): array
    {
        $out = [];
        foreach (preg_split('/[\n;,]+/', $terms) ?: [] as $raw) {
            $norm = trim((string) preg_replace('/\s+/', ' ', self::normalize($raw)));
            if ($norm === '') {
                continue;
            }
            if (str_contains($norm, ' ')) {
                $out[] = 'p:'.$norm;
            } elseif (strlen($norm) >= 2 && ! in_array($norm, self::STOPWORDS, true)) {
                $out[] = 't:'.substr(self::stem($norm), 0, self::MAX_TERM);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Trecho do texto original em volta da primeira ocorrência, com as ocorrências marcadas (<mark>). HTML escapado.
     *
     * @param  list<string>  $terms  termos reduzidos (casam como prefixo de palavra)
     * @param  list<string>  $phrases  frases normalizadas
     */
    public static function snippet(string $original, array $terms, array $phrases, int $radius = 110): string
    {
        $norm = self::normalize($original);
        $chars = mb_str_split($original);
        $ranges = [];
        foreach ($phrases as $p) {
            $pattern = '/(?<![a-z0-9])'.str_replace(' ', '\s+', preg_quote($p, '/')).'(?![a-z0-9])/';
            if (preg_match_all($pattern, $norm, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as [$text, $offset]) {
                    $ranges[] = [$offset, $offset + strlen($text)];
                }
            }
        }
        foreach ($terms as $t) {
            // Casa a palavra cujo radical reduzido começa com o termo (ex.: "dermatite" casa "dermatites").
            if (preg_match_all('/(?<![a-z0-9])[a-z0-9]+/', $norm, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[0] as [$word, $offset]) {
                    if (str_starts_with(self::stem($word), $t) || str_starts_with($word, $t)) {
                        $ranges[] = [$offset, $offset + strlen($word)];
                    }
                }
            }
        }
        // normalize() só gera ASCII, então o deslocamento em bytes == deslocamento em caracteres.
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = count($chars);
        $first = $ranges[0][0] ?? 0;
        $start = max(0, $first - $radius);
        $end = min($total, $first + $radius + 60);
        $html = $start > 0 ? '…' : '';
        $pos = $start;
        foreach ($ranges as [$a, $b]) {
            if ($b <= $pos || $a >= $end) {
                continue;
            }
            $a = max($a, $pos);
            $html .= e(implode('', array_slice($chars, $pos, $a - $pos))).'<mark>'.e(implode('', array_slice($chars, $a, min($b, $end) - $a))).'</mark>';
            $pos = min($b, $end);
        }
        $html .= e(implode('', array_slice($chars, $pos, $end - $pos))).($end < $total ? '…' : '');

        return $html;
    }
}
