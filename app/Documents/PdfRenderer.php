<?php

namespace App\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;

/** HTML (já escapado) → PDF A4 pelo dompdf, sem recursos remotos, PHP ou JavaScript. */
final class PdfRenderer
{
    public static function html(string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);       // nunca busca nada fora do servidor
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(resource_path('pdf'));
        $options->setDefaultFont('DejaVu Sans');    // acentos do português
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
