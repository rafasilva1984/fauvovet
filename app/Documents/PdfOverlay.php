<?php

namespace App\Documents;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Sobrepõe o PDF do documento (gerado pelo dompdf, fundo transparente) à 1ª página do fundo da clínica (papel
 * timbrado/receituário), em todas as páginas, no tamanho A4. Usa o leitor gratuito do FPDI; o fundo já foi
 * "achatado" no envio (PdfXrefFlattener), então aqui só entram PDFs que ele lê.
 */
final class PdfOverlay
{
    public const A4 = [210.0, 297.0];

    public static function compose(string $content, string $background): string
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetCreator('FauvoVet');
        $pdf->SetAutoPageBreak(false);
        $pdf->setSourceFile(StreamReader::createByString($background));
        $bg = $pdf->importPage(1);
        [$x, $y, $w, $h] = self::fit($pdf->getTemplateSize($bg));
        $pages = $pdf->setSourceFile(StreamReader::createByString($content));
        for ($i = 1; $i <= $pages; $i++) {
            $page = $pdf->importPage($i);
            $pdf->AddPage('P', self::A4);
            $pdf->useTemplate($bg, $x, $y, $w, $h);
            $pdf->useTemplate($page, 0, 0, self::A4[0], self::A4[1]);
        }

        return $pdf->Output('S');
    }

    /** O fundo consegue ser lido (1ª página)? Usado na validação do envio. */
    public static function readable(string $background): bool
    {
        try {
            $pdf = new Fpdi('P', 'mm', 'A4');
            $pdf->setSourceFile(StreamReader::createByString($background));
            $pdf->importPage(1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A4 inteiro quando a proporção é parecida (até 5%); senão cabe sem distorcer, centralizado.
     *
     * @param  array{width: float|int, height: float|int}|false  $size
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function fit(array|false $size): array
    {
        [$W, $H] = self::A4;
        if ($size === false || $size['width'] <= 0 || $size['height'] <= 0) {
            return [0, 0, $W, $H];
        }
        $ratio = ($size['width'] / $size['height']) / ($W / $H);
        if (abs($ratio - 1) <= 0.05) {
            return [0, 0, $W, $H];
        }
        $scale = min($W / $size['width'], $H / $size['height']);
        $w = $size['width'] * $scale;
        $h = $size['height'] * $scale;

        return [($W - $w) / 2, ($H - $h) / 2, $w, $h];
    }
}
