<?php

namespace App\Documents;

use RuntimeException;

/**
 * Converte um PDF com "tabela de referências comprimida" (PDF 1.5+: /Type /XRef e /ObjStm, padrão do Word e de
 * muitos geradores) em um PDF equivalente com tabela clássica, que o leitor gratuito do FPDI consegue abrir.
 *
 * Técnica (sem reescrever o original): os bytes originais ficam intactos e, ao FINAL, acrescentamos cada objeto que
 * estava dentro de um /ObjStm como objeto comum, uma tabela "xref" clássica com TODOS os objetos (deslocamentos
 * originais + novos) e um trailer sem /Prev. Arquivos criptografados são recusados. Só leitura de dados — nada é
 * executado.
 */
final class PdfXrefFlattener
{
    private const MAX_SECTIONS = 64;

    private const MAX_OBJECTS = 200_000;

    /** O PDF usa tabela comprimida (precisa achatar)? */
    public static function needsFlattening(string $pdf): bool
    {
        $start = self::startXref($pdf);

        return $start !== null && ! str_starts_with(ltrim(substr($pdf, $start, 32)), 'xref');
    }

    public static function flatten(string $pdf): string
    {
        $start = self::startXref($pdf) ?? throw new RuntimeException('PDF sem "startxref".');

        /** @var array<int, array{0: int, 1: int, 2?: int}> $entries objeto => [tipo, deslocamento|stream, índice] */
        $entries = [];
        $trailer = null;
        $seen = [];
        for ($offset = $start, $n = 0; $offset !== null && $n < self::MAX_SECTIONS; $n++) {
            if (isset($seen[$offset])) {
                break; // /Prev cíclico
            }
            $seen[$offset] = true;
            [$dict, $section, $prev] = str_starts_with(ltrim(substr($pdf, $offset, 32)), 'xref')
                ? self::readClassic($pdf, $offset)
                : self::readStream($pdf, $offset);
            $trailer ??= $dict;
            foreach ($section as $obj => $entry) {
                $entries[$obj] ??= $entry; // a seção mais recente prevalece
            }
            // Arquivo "híbrido": tabela clássica que aponta também para uma tabela comprimida.
            if (preg_match('#/XRefStm\s+(\d+)#', $dict, $m) && ! isset($seen[(int) $m[1]])) {
                $seen[(int) $m[1]] = true;
                foreach (self::readStream($pdf, (int) $m[1])[1] as $obj => $entry) {
                    $entries[$obj] ??= $entry;
                }
            }
            $offset = $prev;
        }
        if (preg_match('#/Encrypt\b#', $trailer)) {
            throw new RuntimeException('PDF protegido por senha/criptografia não é aceito.');
        }
        if (! preg_match('#/Root\s+(\d+\s+\d+\s+R)#', $trailer, $root)) {
            throw new RuntimeException('PDF sem catálogo (/Root).');
        }
        if (count($entries) > self::MAX_OBJECTS) {
            throw new RuntimeException('PDF com objetos demais.');
        }

        $out = rtrim($pdf)."\n";
        $offsets = [];
        $streams = [];
        foreach ($entries as $obj => $entry) {
            if ($entry[0] === 1) {
                $offsets[$obj] = [$entry[1], $entry[2] ?? 0];
            }
        }
        foreach ($entries as $obj => $entry) {
            if ($entry[0] !== 2) {
                continue;
            }
            $streams[$entry[1]] ??= self::objectStream($pdf, $entry[1], $offsets);
            if (! isset($streams[$entry[1]][$obj])) {
                continue;
            }
            $body = $streams[$entry[1]][$obj];
            $offsets[$obj] = [strlen($out), 0];
            $out .= $obj." 0 obj\n".$body."\nendobj\n";
        }

        ksort($offsets);
        $size = max(array_keys($offsets ?: [0 => 0])) + 1;
        $xref = strlen($out);
        $out .= "xref\n0 ".$size."\n0000000000 65535 f\r\n";
        for ($i = 1; $i < $size; $i++) {
            $out .= isset($offsets[$i])
                ? sprintf("%010d %05d n\r\n", $offsets[$i][0], $offsets[$i][1])
                : "0000000000 00000 f\r\n";
        }
        $info = preg_match('#/Info\s+(\d+\s+\d+\s+R)#', $trailer, $m) ? ' /Info '.$m[1] : '';

        return $out.'trailer << /Size '.$size.' /Root '.$root[1].$info." >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function startXref(string $pdf): ?int
    {
        $pos = strrpos($pdf, 'startxref');
        if ($pos === false || ! preg_match('#startxref\s+(\d+)#', $pdf, $m, 0, $pos)) {
            return null;
        }

        return (int) $m[1] < strlen($pdf) ? (int) $m[1] : null;
    }

    /** @return array{0: string, 1: array<int, array{0: int, 1: int, 2: int}>, 2: ?int} */
    private static function readClassic(string $pdf, int $offset): array
    {
        $end = strpos($pdf, 'trailer', $offset) ?: throw new RuntimeException('Tabela clássica sem trailer.');
        $lines = preg_split('/\r\n|\r|\n/', trim(substr($pdf, $offset + 4, $end - $offset - 4))) ?: [];
        $entries = [];
        $obj = 0;
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) === 2) {
                $obj = (int) $parts[0];
            } elseif (count($parts) === 3) {
                if ($parts[2] === 'n') {
                    $entries[$obj] = [1, (int) $parts[0], (int) $parts[1]];
                }
                $obj++;
            }
        }
        $dict = self::dictAt($pdf, $end + 7);

        return [$dict, $entries, preg_match('#/Prev\s+(\d+)#', $dict, $m) ? (int) $m[1] : null];
    }

    /** @return array{0: string, 1: array<int, array{0: int, 1: int, 2?: int}>, 2: ?int} */
    private static function readStream(string $pdf, int $offset): array
    {
        [$dict, $data] = self::streamAt($pdf, $offset, []);
        if (! preg_match('#/W\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s*\]#', $dict, $w) || ! preg_match('#/Size\s+(\d+)#', $dict, $size)) {
            throw new RuntimeException('Tabela comprimida inválida.');
        }
        $widths = [(int) $w[1], (int) $w[2], (int) $w[3]];
        $index = preg_match('#/Index\s*\[([\d\s]+)\]#', $dict, $m) ? array_map('intval', preg_split('/\s+/', trim($m[1])) ?: []) : [0, (int) $size[1]];
        $row = array_sum($widths);
        $entries = [];
        $pos = 0;
        for ($i = 0; $i + 1 < count($index); $i += 2) {
            for ($obj = $index[$i], $last = $index[$i] + $index[$i + 1]; $obj < $last && $pos + $row <= strlen($data); $obj++, $pos += $row) {
                $f = [];
                for ($k = 0, $p = $pos; $k < 3; $p += $widths[$k], $k++) {
                    $f[$k] = $widths[$k] === 0 ? ($k === 0 ? 1 : 0) : self::int(substr($data, $p, $widths[$k]));
                }
                if ($f[0] === 1) {
                    $entries[$obj] = [1, $f[1], $f[2]];
                } elseif ($f[0] === 2) {
                    $entries[$obj] = [2, $f[1], $f[2]];
                }
            }
        }

        return [$dict, $entries, preg_match('#/Prev\s+(\d+)#', $dict, $p) ? (int) $p[1] : null];
    }

    /**
     * Objetos de um /ObjStm: número => texto do objeto.
     *
     * @param  array<int, array{0: int, 1: int}>  $offsets
     * @return array<int, string>
     */
    private static function objectStream(string $pdf, int $obj, array $offsets): array
    {
        if (! isset($offsets[$obj])) {
            return [];
        }
        [$dict, $data] = self::streamAt($pdf, $offsets[$obj][0], $offsets);
        if (! preg_match('#/N\s+(\d+)#', $dict, $n) || ! preg_match('#/First\s+(\d+)#', $dict, $first)) {
            return [];
        }
        $header = preg_split('/\s+/', trim(substr($data, 0, (int) $first[1]))) ?: [];
        $pairs = [];
        for ($i = 0; $i + 1 < count($header) && $i < 2 * (int) $n[1]; $i += 2) {
            $pairs[] = [(int) $header[$i], (int) $first[1] + (int) $header[$i + 1]];
        }
        $objects = [];
        foreach ($pairs as $k => [$num, $start]) {
            $end = $pairs[$k + 1][1] ?? strlen($data);
            $objects[$num] = trim(substr($data, $start, max(0, $end - $start)));
        }

        return $objects;
    }

    /**
     * Dicionário e dados decodificados do stream do objeto que começa em $offset.
     *
     * @param  array<int, array{0: int, 1: int}>  $offsets  para resolver /Length indireto
     * @return array{0: string, 1: string}
     */
    private static function streamAt(string $pdf, int $offset, array $offsets): array
    {
        if (! preg_match('#\G\s*\d+\s+\d+\s+obj\s*#', $pdf, $m, 0, $offset)) {
            throw new RuntimeException('Objeto não encontrado no deslocamento indicado.');
        }
        $dictStart = $offset + strlen($m[0]);
        $dict = self::dictAt($pdf, $dictStart);
        $after = $dictStart + strlen($dict);
        if (! preg_match('#\G\s*stream\r?\n#', $pdf, $s, 0, $after)) {
            throw new RuntimeException('Stream ausente.');
        }
        $dataStart = $after + strlen($s[0]);
        if (preg_match('#/Length\s+(\d+)\s+(\d+)\s+R#', $dict, $ref)) {
            $refOffset = $offsets[(int) $ref[1]][0] ?? null;
            $length = $refOffset !== null && preg_match('#\G\s*\d+\s+\d+\s+obj\s*(\d+)#', $pdf, $l, 0, $refOffset) ? (int) $l[1] : null;
        } else {
            $length = preg_match('#/Length\s+(\d+)#', $dict, $l) ? (int) $l[1] : null;
        }
        $length ??= max(0, (int) strpos($pdf, 'endstream', $dataStart) - $dataStart);
        $data = substr($pdf, $dataStart, $length);

        if (preg_match('#/Filter\s*\[?\s*/FlateDecode\s*\]?#', $dict)) {
            $data = @gzuncompress($data, 64 * 1024 * 1024);
            if ($data === false) {
                throw new RuntimeException('Falha ao descompactar a tabela do PDF.');
            }
        } elseif (preg_match('#/Filter#', $dict)) {
            throw new RuntimeException('Compressão de tabela não suportada.');
        }
        if (preg_match('#/Predictor\s+(\d+)#', $dict, $pr) && (int) $pr[1] >= 10) {
            $data = self::unpredict($data, preg_match('#/Columns\s+(\d+)#', $dict, $c) ? (int) $c[1] : 1);
        }

        return [$dict, $data];
    }

    /** Lê "<< ... >>" com aninhamento, a partir de $pos. */
    private static function dictAt(string $pdf, int $pos): string
    {
        $start = strpos($pdf, '<<', $pos);
        if ($start === false) {
            throw new RuntimeException('Dicionário não encontrado.');
        }
        $depth = 0;
        for ($i = $start, $len = strlen($pdf); $i < $len - 1; $i++) {
            $two = $pdf[$i].$pdf[$i + 1];
            if ($two === '<<') {
                $depth++;
                $i++;
            } elseif ($two === '>>') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return substr($pdf, $pos, $i + 1 - $pos);
                }
            }
        }

        throw new RuntimeException('Dicionário incompleto.');
    }

    /** Desfaz o preditor PNG (filtros 0–4) por linha. */
    private static function unpredict(string $data, int $columns): string
    {
        $out = '';
        $prev = array_fill(0, $columns, 0);
        for ($pos = 0, $len = strlen($data); $pos + $columns < $len + 1 && $pos < $len; $pos += $columns + 1) {
            $type = ord($data[$pos]);
            $row = array_values(unpack('C*', substr($data, $pos + 1, $columns).str_repeat("\0", max(0, $columns - strlen(substr($data, $pos + 1, $columns))))) ?: []);
            for ($i = 0; $i < $columns; $i++) {
                $left = $i > 0 ? $row[$i - 1] : 0;
                $up = $prev[$i];
                $upLeft = $i > 0 ? $prev[$i - 1] : 0;
                $row[$i] = ($row[$i] + match ($type) {
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => self::paeth($left, $up, $upLeft),
                    default => 0,
                }) & 0xFF;
            }
            $out .= pack('C*', ...$row);
            $prev = $row;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    private static function int(string $bytes): int
    {
        $v = 0;
        foreach (str_split($bytes) as $b) {
            $v = ($v << 8) | ord($b);
        }

        return $v;
    }
}
