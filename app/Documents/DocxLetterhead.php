<?php

namespace App\Documents;

use App\Files\ZipReader;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Papel timbrado em Word (DOCX) → HTML de uma página A4 com o que forma o "timbre": cabeçalho e rodapé (textos e
 * imagens, com alinhamento) e imagens ancoradas atrás do texto no corpo (fundo de página). O texto do corpo NÃO
 * entra (é conteúdo, não timbre). Não há LibreOffice/Word no servidor: a reprodução é aproximada e a tela mostra a
 * prévia para conferência. Imagens EMF/WMF/SVG não são suportadas (aviso).
 */
final class DocxLetterhead
{
    private const NS = [
        'w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
        'wp' => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
        'v' => 'urn:schemas-microsoft-com:vml',
        'rel' => 'http://schemas.openxmlformats.org/package/2006/relationships',
    ];

    private const IMAGE_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    /** @var list<string> */
    private array $notes = [];

    private int $elements = 0;

    /** Página em mm (A4 por padrão) e margens do Word em mm. */
    private float $pageW = 210;

    private float $pageH = 297;

    /** @var array{top: float, bottom: float, left: float, right: float, header: float, footer: float} */
    private array $mar = ['top' => 25, 'bottom' => 25, 'left' => 30, 'right' => 30, 'header' => 12.5, 'footer' => 12.5];

    private function __construct(private readonly string $zip) {}

    /**
     * @return array{html: string, margins: array{top: int, bottom: int, side: int}, notes: list<string>, elements: int}
     */
    public static function convert(string $docx): array
    {
        return (new self($docx))->run();
    }

    /** @return array{html: string, margins: array{top: int, bottom: int, side: int}, notes: list<string>, elements: int} */
    private function run(): array
    {
        $doc = $this->xml('word/document.xml') ?? throw new \RuntimeException('DOCX sem word/document.xml.');
        $x = $this->xpath($doc);
        $rels = $this->rels('word/document.xml');

        $sect = $x->query('//w:body/w:sectPr')->item(0) ?? $x->query('//w:sectPr')->item(0);
        $headerPart = $footerPart = null;
        if ($sect instanceof DOMElement) {
            $size = $x->query('w:pgSz', $sect)->item(0);
            if ($size instanceof DOMElement && (int) $size->getAttributeNS(self::NS['w'], 'w') > 0) {
                $this->pageW = $this->twip((int) $size->getAttributeNS(self::NS['w'], 'w'));
                $this->pageH = $this->twip((int) $size->getAttributeNS(self::NS['w'], 'h'));
            }
            $mar = $x->query('w:pgMar', $sect)->item(0);
            if ($mar instanceof DOMElement) {
                foreach (array_keys($this->mar) as $k) {
                    if ($mar->hasAttributeNS(self::NS['w'], $k)) {
                        $this->mar[$k] = abs($this->twip((int) $mar->getAttributeNS(self::NS['w'], $k)));
                    }
                }
            }
            $headerPart = $this->partFor($x, $sect, 'headerReference', $rels);
            $footerPart = $this->partFor($x, $sect, 'footerReference', $rels);
        }
        if (abs($this->pageW - 210) > 3 || abs($this->pageH - 297) > 3) {
            $this->notes[] = sprintf('A página do Word mede %.0f × %.0f mm; o fundo foi ajustado para A4.', $this->pageW, $this->pageH);
        }

        $html = '';
        if ($headerPart !== null) {
            $html .= $this->block($headerPart, 'top', $this->mar['header']);
        }
        if ($footerPart !== null) {
            $html .= $this->block($footerPart, 'bottom', $this->mar['footer']);
        }
        // Imagens ancoradas no corpo (ex.: timbre de página inteira "atrás do texto").
        foreach ($x->query('//w:body//wp:anchor') as $anchor) {
            if ($anchor instanceof DOMElement) {
                $html .= $this->anchored($x, $anchor, $rels, 0);
            }
        }
        if ($this->elements === 0) {
            throw new \RuntimeException('Não encontramos cabeçalho, rodapé nem imagem de fundo neste Word. Envie o timbre em PDF ou como imagem.');
        }
        $this->notes[] = 'Convertido do Word sem o Microsoft Word: posições e fontes são aproximadas. Confira a prévia.';

        $sx = 210 / $this->pageW;
        $sy = 297 / $this->pageH;
        $page = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{margin:0}body{margin:0;font-family:"DejaVu Sans",sans-serif;color:#000}'
            .'.p{margin:0;line-height:1.2}.blk{position:absolute;left:'.$this->mm($this->mar['left'] * $sx).';right:'.$this->mm($this->mar['right'] * $sx).'}'
            .'img{display:inline-block}</style></head><body>'.$html.'</body></html>';

        return [
            'html' => $page,
            'margins' => [
                'top' => (int) round(max($this->mar['top'], 10) * $sy),
                'bottom' => (int) round(max($this->mar['bottom'], 10) * $sy),
                'side' => (int) round(max(min($this->mar['left'], $this->mar['right']), 10) * $sx),
            ],
            'notes' => $this->notes,
            'elements' => $this->elements,
        ];
    }

    /** Cabeçalho/rodapé: parágrafos em fluxo dentro de um bloco preso ao topo/base da página. */
    private function block(string $part, string $edge, float $distance): string
    {
        $dom = $this->xml($part);
        if ($dom === null) {
            return '';
        }
        $x = $this->xpath($dom);
        $rels = $this->rels($part);
        $flow = '';
        $absolute = '';
        foreach ($x->query('//w:p') as $p) {
            if (! $p instanceof DOMElement) {
                continue;
            }
            $align = match ($x->evaluate('string(w:pPr/w:jc/@w:val)', $p)) {
                'center' => 'center', 'right', 'end' => 'right', 'both' => 'justify', default => 'left',
            };
            $inner = '';
            foreach ($x->query('.//w:r | .//wp:anchor', $p) as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                if ($node->localName === 'anchor') {
                    $absolute .= $this->anchored($x, $node, $rels, $edge === 'top' ? $distance : $this->pageH - $distance);

                    continue;
                }
                $inner .= $this->runHtml($x, $node, $rels);
            }
            if (trim(strip_tags($inner, '<img>')) !== '') {
                $flow .= '<p class="p" style="text-align:'.$align.'">'.$inner.'</p>';
            }
        }
        $sy = 297 / $this->pageH;
        $pos = $edge === 'top' ? 'top:'.$this->mm($distance * $sy) : 'bottom:'.$this->mm($distance * $sy);

        return ($flow !== '' ? '<div class="blk" style="'.$pos.'">'.$flow.'</div>' : '').$absolute;
    }

    /**
     * Um "run": texto (tamanho/negrito/cor) e imagens em linha (DrawingML ou VML).
     *
     * @param  array<string, string>  $rels
     */
    private function runHtml(DOMXPath $x, DOMElement $r, array $rels): string
    {
        $out = '';
        $size = (int) $x->evaluate('string(w:rPr/w:sz/@w:val)', $r);
        $color = (string) $x->evaluate('string(w:rPr/w:color/@w:val)', $r);
        $style = 'font-size:'.($size > 0 ? $size / 2 : 11).'pt;';
        if ($x->query('w:rPr/w:b[not(@w:val="0") and not(@w:val="false")]', $r)->length > 0) {
            $style .= 'font-weight:bold;';
        }
        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
            $style .= 'color:#'.$color.';';
        }
        foreach ($x->query('w:t | w:tab | w:br | w:drawing//wp:inline | w:pict//v:imagedata', $r) as $n) {
            if (! $n instanceof DOMElement) {
                continue;
            }
            switch ($n->localName) {
                case 't':
                    if (trim($n->textContent) !== '') {
                        $this->elements++;
                    }
                    $out .= '<span style="'.$style.'">'.htmlspecialchars($n->textContent, ENT_QUOTES, 'UTF-8').'</span>';
                    break;
                case 'tab':
                    $out .= '&#160;&#160;&#160;&#160;';
                    break;
                case 'br':
                    $out .= '<br>';
                    break;
                case 'inline':
                    $w = (int) $x->evaluate('string(wp:extent/@cx)', $n) / 36000;
                    $h = (int) $x->evaluate('string(wp:extent/@cy)', $n) / 36000;
                    $out .= $this->img((string) $x->evaluate('string(.//a:blip/@r:embed)', $n), $rels, 'width:'.$this->mm($w).';height:'.$this->mm($h));
                    break;
                case 'imagedata':
                    $shape = $n->parentNode instanceof DOMElement ? $n->parentNode->getAttribute('style') : '';
                    $out .= $this->vml($shape, $n->getAttributeNS(self::NS['r'], 'id'), $rels);
                    break;
            }
        }

        return $out;
    }

    /**
     * Imagem ancorada (posição absoluta na página). $paragraphTop = referência vertical aproximada.
     *
     * @param  array<string, string>  $rels
     */
    private function anchored(DOMXPath $x, DOMElement $a, array $rels, float $paragraphTop): string
    {
        $w = (int) $x->evaluate('string(wp:extent/@cx)', $a) / 36000;
        $h = (int) $x->evaluate('string(wp:extent/@cy)', $a) / 36000;
        $left = $this->axis($x, $a, 'H', $w, $paragraphTop);
        $top = $this->axis($x, $a, 'V', $h, $paragraphTop);
        $sx = 210 / $this->pageW;
        $sy = 297 / $this->pageH;

        return $this->img((string) $x->evaluate('string(.//a:blip/@r:embed)', $a), $rels,
            'position:absolute;left:'.$this->mm($left * $sx).';top:'.$this->mm($top * $sy).';width:'.$this->mm($w * $sx).';height:'.$this->mm($h * $sy));
    }

    /** Posição (mm, a partir da borda da página) no eixo H ou V de uma âncora. */
    private function axis(DOMXPath $x, DOMElement $a, string $axis, float $size, float $paragraphTop): float
    {
        $pos = $x->query('wp:position'.$axis, $a)->item(0);
        if (! $pos instanceof DOMElement) {
            return 0;
        }
        $rel = $pos->getAttribute('relativeFrom');
        $isH = $axis === 'H';
        [$origin, $extent] = match ($rel) {
            'page' => [0, $isH ? $this->pageW : $this->pageH],
            'margin', 'column', 'insideMargin', 'outsideMargin' => $isH
                ? [$this->mar['left'], $this->pageW - $this->mar['left'] - $this->mar['right']]
                : [$this->mar['top'], $this->pageH - $this->mar['top'] - $this->mar['bottom']],
            'topMargin' => [0, $this->mar['top']],
            'bottomMargin' => [$this->pageH - $this->mar['bottom'], $this->mar['bottom']],
            'leftMargin' => [0, $this->mar['left']],
            'rightMargin' => [$this->pageW - $this->mar['right'], $this->mar['right']],
            default => $isH ? [$this->mar['left'], $this->pageW - $this->mar['left'] - $this->mar['right']] : [$paragraphTop, 0],
        };
        $offset = $x->evaluate('string(wp:posOffset)', $pos);
        if ($offset !== '') {
            return $origin + (int) $offset / 36000;
        }

        return match ((string) $x->evaluate('string(wp:align)', $pos)) {
            'center' => $origin + ($extent - $size) / 2,
            'right', 'bottom', 'outside' => $origin + $extent - $size,
            default => $origin,
        };
    }

    /**
     * Imagem VML (Word antigo): largura/altura e margens em pt no atributo style.
     *
     * @param  array<string, string>  $rels
     */
    private function vml(string $style, string $rid, array $rels): string
    {
        $get = fn (string $k) => preg_match('/(?:^|;)\s*'.preg_quote($k, '/').'\s*:\s*(-?[\d.]+)pt/i', $style, $m) ? (float) $m[1] * 0.3528 : null;
        $w = $get('width') ?? 40;
        $h = $get('height') ?? 20;
        if (str_contains($style, 'position:absolute')) {
            $relH = str_contains($style, 'mso-position-horizontal-relative:page') ? 0 : $this->mar['left'];
            $relV = str_contains($style, 'mso-position-vertical-relative:page') ? 0 : $this->mar['header'];
            $sx = 210 / $this->pageW;
            $sy = 297 / $this->pageH;

            return $this->img($rid, $rels, 'position:absolute;left:'.$this->mm(($relH + ($get('margin-left') ?? 0)) * $sx).';top:'
                .$this->mm(($relV + ($get('margin-top') ?? 0)) * $sy).';width:'.$this->mm($w * $sx).';height:'.$this->mm($h * $sy));
        }

        return $this->img($rid, $rels, 'width:'.$this->mm($w).';height:'.$this->mm($h));
    }

    /** @param array<string, string> $rels */
    private function img(string $rid, array $rels, string $style): string
    {
        $target = $rels[$rid] ?? null;
        if ($target === null) {
            return '';
        }
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mime = self::IMAGE_MIME[$ext] ?? null;
        if ($mime === null) {
            $this->notes[] = 'Imagem em formato '.strtoupper($ext).' não é suportada e ficou de fora — salve o logotipo como PNG/JPG no Word ou envie o timbre em PDF.';

            return '';
        }
        $bytes = ZipReader::entry($this->zip, $target);
        if ($bytes === null || ! self::looksLikeImage($bytes, $mime)) {
            return '';
        }
        $this->elements++;

        return '<img src="data:'.$mime.';base64,'.base64_encode($bytes).'" style="'.$style.'" alt="">';
    }

    private static function looksLikeImage(string $bytes, string $mime): bool
    {
        return match ($mime) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n"),
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/gif' => str_starts_with($bytes, 'GIF8'),
            default => false,
        };
    }

    /**
     * Parte do cabeçalho/rodapé "default" (ou a primeira) da seção.
     *
     * @param  array<string, string>  $rels
     */
    private function partFor(DOMXPath $x, DOMElement $sect, string $element, array $rels): ?string
    {
        $ref = $x->query('w:'.$element.'[@w:type="default"]', $sect)->item(0) ?? $x->query('w:'.$element, $sect)->item(0);

        return $ref instanceof DOMElement ? ($rels[$ref->getAttributeNS(self::NS['r'], 'id')] ?? null) : null;
    }

    /**
     * Relações da parte: Id => caminho no ZIP (ex.: "word/media/image1.png").
     *
     * @return array<string, string>
     */
    private function rels(string $part): array
    {
        $dir = dirname($part);
        $dom = $this->xml($dir.'/_rels/'.basename($part).'.rels');
        if ($dom === null) {
            return [];
        }
        $out = [];
        foreach ($dom->getElementsByTagNameNS(self::NS['rel'], 'Relationship') as $rel) {
            if ($rel->getAttribute('TargetMode') === 'External') {
                continue; // nunca busca nada fora do arquivo
            }
            $target = $rel->getAttribute('Target');
            $path = str_starts_with($target, '/') ? ltrim($target, '/') : self::normalize($dir.'/'.$target);
            $out[$rel->getAttribute('Id')] = $path;
        }

        return $out;
    }

    private static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                array_pop($parts);
            } elseif ($seg !== '.' && $seg !== '') {
                $parts[] = $seg;
            }
        }

        return implode('/', $parts);
    }

    private function xml(string $entry): ?DOMDocument
    {
        $raw = ZipReader::entry($this->zip, $entry);
        if ($raw === null || str_contains($raw, '<!DOCTYPE')) {
            return null; // DOCX legítimo não tem DOCTYPE (evita entidades/XXE)
        }
        $dom = new DOMDocument;

        return @$dom->loadXML($raw, LIBXML_NONET | LIBXML_COMPACT) ? $dom : null;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $x = new DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $x->registerNamespace($prefix, $uri);
        }

        return $x;
    }

    private function twip(int $v): float
    {
        return $v / 1440 * 25.4;
    }

    private function mm(float $v): string
    {
        return round($v, 2).'mm';
    }
}
