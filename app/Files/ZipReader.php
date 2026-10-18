<?php

namespace App\Files;

/**
 * Leitor mínimo de ZIP (DOCX) pelo diretório central — não depende da extensão zip do PHP, ausente em algumas
 * hospedagens. Só entradas "armazenadas" ou "deflate"; limite de tamanho descompactado contra "zip bomb".
 */
final class ZipReader
{
    public const MAX_ENTRY_BYTES = 30 * 1024 * 1024;

    /** Conteúdo de uma entrada, ou null se ausente/ilegível. */
    public static function entry(string $zip, string $name): ?string
    {
        foreach (self::directory($zip) as $h) {
            if ($h['name'] !== $name) {
                continue;
            }
            if ($h['usize'] > self::MAX_ENTRY_BYTES || strlen($zip) < $h['offset'] + 30) {
                return null;
            }
            /** @var array{nlen: int, elen: int} $local */
            $local = unpack('vnlen/velen', substr($zip, $h['offset'] + 26, 4));
            $data = substr($zip, $h['offset'] + 30 + $local['nlen'] + $local['elen'], $h['csize']);

            return match ($h['method']) {
                0 => $data,
                8 => ($out = @gzinflate($data, self::MAX_ENTRY_BYTES)) === false ? null : $out,
                default => null,
            };
        }

        return null;
    }

    /**
     * Entradas do diretório central.
     *
     * @return list<array{name: string, method: int, csize: int, usize: int, offset: int}>
     */
    public static function directory(string $zip): array
    {
        $eocd = strrpos($zip, "PK\x05\x06");
        if ($eocd === false || strlen($zip) < $eocd + 22) {
            return [];
        }
        /** @var array{entries: int, cdsize: int, cdoffset: int} $end */
        $end = unpack('ventries/Vcdsize/Vcdoffset', substr($zip, $eocd + 10, 10));
        $pos = $end['cdoffset'];
        $list = [];
        for ($i = 0; $i < $end['entries'] && $pos + 46 <= strlen($zip); $i++) {
            if (substr($zip, $pos, 4) !== "PK\x01\x02") {
                break;
            }
            /** @var array{method: int, csize: int, usize: int, nlen: int, elen: int, clen: int, offset: int} $h */
            $h = unpack('vmethod', substr($zip, $pos + 10, 2)) + unpack('Vcsize/Vusize', substr($zip, $pos + 20, 8))
                + unpack('vnlen/velen/vclen', substr($zip, $pos + 28, 6)) + unpack('Voffset', substr($zip, $pos + 42, 4));
            $list[] = ['name' => substr($zip, $pos + 46, $h['nlen']), 'method' => $h['method'], 'csize' => $h['csize'], 'usize' => $h['usize'], 'offset' => $h['offset']];
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        }

        return $list;
    }
}
