<?php

namespace App\Files;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extração de texto de anexos para a busca clínica (ADR-018). Nunca altera o original.
 *  - PDF: smalot/pdfparser (PHP puro). PDF digitalizado (só imagem) vira "no_text".
 *  - DOCX: leitor de ZIP próprio (não depende da extensão zip) + word/document.xml.
 *  - Imagens: sem OCR nesta versão → "no_text" (sem texto pesquisável).
 */
final class TextExtractor
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_CHARS = 200_000;

    /** Abaixo disso (letras/números), consideramos que não há texto útil (ex.: PDF escaneado com rodapé). */
    private const MIN_USEFUL = 20;

    public const STATUS = [
        'indexed' => 'Texto pesquisável',
        'no_text' => 'Sem texto pesquisável',
        'failed' => 'Falha ao ler o texto',
        'too_large' => 'Grande demais para leitura',
    ];

    /** @return array{status: string, text: ?string} */
    public static function extract(string $bytes, string $extension): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            return ['status' => 'too_large', 'text' => null];
        }
        try {
            $text = match ($extension) {
                'pdf' => self::pdf($bytes),
                'docx' => self::docx($bytes),
                default => '',
            };
        } catch (Throwable) {
            return ['status' => 'failed', 'text' => null];
        }
        $text = trim((string) preg_replace(["/[ \t\x{00A0}]+/u", "/\n{3,}/"], [' ', "\n\n"], str_replace("\r", '', $text)));
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        if (preg_match_all('/[\p{L}\p{N}]/u', $text) < self::MIN_USEFUL) {
            return ['status' => 'no_text', 'text' => null];
        }

        return ['status' => 'indexed', 'text' => mb_substr($text, 0, self::MAX_CHARS)];
    }

    private static function pdf(string $bytes): string
    {
        $config = new Config;
        $config->setRetainImageContent(false); // não carrega imagens na memória
        $config->setDecodeMemoryLimit(32 * 1024 * 1024);

        return (new Parser([], $config))->parseContent($bytes)->getText();
    }

    private static function docx(string $bytes): string
    {
        $xml = self::zipEntry($bytes, 'word/document.xml');
        if ($xml === null) {
            return '';
        }
        $xml = (string) preg_replace(['#</w:p>#', '#<w:(tab|br)\b[^>]*/>#'], ["\n", ' '], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Lê uma entrada do ZIP pelo diretório central (armazenada ou deflate). */
    private static function zipEntry(string $zip, string $name): ?string
    {
        $eocd = strrpos($zip, "PK\x05\x06");
        if ($eocd === false || strlen($zip) < $eocd + 22) {
            return null;
        }
        /** @var array{entries: int, cdsize: int, cdoffset: int} $end */
        $end = unpack('ventries/Vcdsize/Vcdoffset', substr($zip, $eocd + 10, 10));
        $pos = $end['cdoffset'];
        for ($i = 0; $i < $end['entries'] && $pos + 46 <= strlen($zip); $i++) {
            if (substr($zip, $pos, 4) !== "PK\x01\x02") {
                return null;
            }
            /** @var array{method: int, csize: int, usize: int, nlen: int, elen: int, clen: int, offset: int} $h */
            $h = unpack('vmethod', substr($zip, $pos + 10, 2)) + unpack('Vcsize/Vusize', substr($zip, $pos + 20, 8))
                + unpack('vnlen/velen/vclen', substr($zip, $pos + 28, 6)) + unpack('Voffset', substr($zip, $pos + 42, 4));
            $entry = substr($zip, $pos + 46, $h['nlen']);
            $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
            if ($entry !== $name) {
                continue;
            }
            if ($h['usize'] > self::MAX_BYTES * 3) {
                return null; // proteção contra "zip bomb"
            }
            /** @var array{nlen: int, elen: int} $local */
            $local = unpack('vnlen/velen', substr($zip, $h['offset'] + 26, 4));
            $data = substr($zip, $h['offset'] + 30 + $local['nlen'] + $local['elen'], $h['csize']);

            return match ($h['method']) {
                0 => $data,
                8 => ($out = @gzinflate($data, self::MAX_BYTES * 3)) === false ? null : $out,
                default => null,
            };
        }

        return null;
    }
}
