<?php

namespace App\Files;

/**
 * Detecta o tipo REAL do arquivo pelo conteúdo (assinatura/libmagic) e só aceita a lista fechada abaixo, com a
 * extensão coerente. Extensão sozinha nunca basta: "script.php" renomeado para ".pdf" é recusado.
 */
final class FileTypeDetector
{
    /** mime real => [extensões aceitas, extensão gravada, rótulo] */
    public const ALLOWED = [
        'application/pdf' => [['pdf'], 'pdf', 'PDF'],
        'image/jpeg' => [['jpg', 'jpeg'], 'jpg', 'Imagem JPG'],
        'image/png' => [['png'], 'png', 'Imagem PNG'],
        'image/webp' => [['webp'], 'webp', 'Imagem WebP'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [['docx'], 'docx', 'Word (DOCX)'],
    ];

    /**
     * @return array{mime: string, extension: string}|null null = tipo não aceito
     */
    public static function detect(string $path, string $originalName): ?array
    {
        $ext = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $head = (string) file_get_contents($path, false, null, 0, 16);

        // DOCX é um ZIP: algumas versões do libmagic dizem só "zip"/"octet-stream". Confere a estrutura do Word.
        if ($ext === 'docx' && in_array($mime, ['application/zip', 'application/octet-stream'], true)
            && str_starts_with($head, "PK\x03\x04") && str_contains((string) file_get_contents($path, false, null, 0, 65536), 'word/')) {
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        // Assinaturas mínimas como segunda conferência (o libmagic já olhou o conteúdo).
        $signatureOk = match ($mime) {
            'application/pdf' => str_starts_with($head, '%PDF-'),
            'image/jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($head, "\x89PNG\r\n\x1A\n"),
            'image/webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => str_starts_with($head, "PK\x03\x04"),
            default => false,
        };
        if (! $signatureOk || ! in_array($ext, self::ALLOWED[$mime][0], true)) { // $signatureOk só é true para tipos da lista
            return null;
        }

        return ['mime' => $mime, 'extension' => self::ALLOWED[$mime][1]];
    }

    /** Nome seguro para exibir/baixar: sem caminho, sem caracteres de controle, tamanho limitado. */
    public static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"<>:|?*]+/u', '', $name);

        return mb_substr(trim($name) !== '' ? trim($name) : 'arquivo', 0, 200);
    }
}
