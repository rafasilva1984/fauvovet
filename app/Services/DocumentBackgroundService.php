<?php

namespace App\Services;

use App\Documents\DocxLetterhead;
use App\Documents\PdfOverlay;
use App\Documents\PdfRenderer;
use App\Documents\PdfXrefFlattener;
use App\Files\FileTypeDetector;
use App\Models\DocumentBackground;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Papel timbrado e receituário da clínica (ADR-020). Ao enviar:
 *  1. confere o tipo pelo conteúdo (PDF, DOCX, JPG ou PNG) e o tamanho; 2. guarda o original em disco privado com
 *     SHA-256; 3. prepara o "fundo pronto" (PDF A4, 1 página): PDF → 1ª página (tabela achatada se preciso);
 *     DOCX → cabeçalho/rodapé/imagens de fundo convertidos; imagem → página inteira; 4. aposenta o fundo anterior
 *     do mesmo tipo. O novo fundo passa a valer imediatamente para os documentos que usam "Automático".
 */
final class DocumentBackgroundService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const ACCEPTED = ['pdf', 'docx', 'jpg', 'png'];

    public function __construct(private readonly TenantContext $context, private readonly Audit $audit) {}

    /** @return array<string, DocumentBackground> tipo => fundo atual */
    public function current(): array
    {
        return DocumentBackground::query()->current()->get()->keyBy('kind')->all();
    }

    /**
     * Fundo a usar para um documento: escolha explícita, senão automático (receituário nas receitas, timbrado nos
     * demais; se o preferido não existe, usa o outro). "none" = sem fundo.
     */
    public function resolve(string $choice, string $category): ?DocumentBackground
    {
        if ($choice === 'none') {
            return null;
        }
        $current = $this->current();
        if (isset($current[$choice])) {
            return $current[$choice];
        }
        $order = $category === 'prescription' ? ['prescription', 'letterhead'] : ['letterhead', 'prescription'];
        foreach ($order as $kind) {
            if (isset($current[$kind])) {
                return $current[$kind];
            }
        }

        return null;
    }

    public function upload(string $kind, UploadedFile $file, User $actor): DocumentBackground
    {
        if (! isset(DocumentBackground::KINDS[$kind])) {
            abort(404);
        }
        $original = FileTypeDetector::safeName($file->getClientOriginalName());
        $type = FileTypeDetector::detect((string) $file->getRealPath(), $original);
        if ($type === null || ! in_array($type['extension'], self::ACCEPTED, true)) {
            throw ValidationException::withMessages(['file' => 'Envie PDF, Word (DOCX), JPG ou PNG. O conteúdo precisa corresponder à extensão.']);
        }
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Arquivo acima de 10 MB.']);
        }

        return $this->store($kind, $bytes, $type, $original, $actor);
    }

    /**
     * Prepara e grava (também usado pela demonstração).
     *
     * @param  array{mime: string, extension: string}  $type
     */
    public function store(string $kind, string $bytes, array $type, string $original, User $actor, ?string $id = null): DocumentBackground
    {
        [$render, $margins, $notes] = $this->prepare($bytes, $type['extension']);
        $tenant = $this->context->requireId();
        $id ??= (string) Str::ulid();
        $sourcePath = sprintf('backgrounds/%s/%s-original.%s', $tenant, $id, $type['extension']);
        $renderPath = sprintf('backgrounds/%s/%s-fundo.pdf', $tenant, $id);
        Storage::disk('local')->put($sourcePath, $bytes);
        Storage::disk('local')->put($renderPath, $render);

        try {
            $bg = DB::transaction(function () use ($id, $kind, $type, $original, $bytes, $sourcePath, $renderPath, $render, $margins, $notes, $actor) {
                DocumentBackground::query()->current()->where('kind', $kind)->lockForUpdate()->get()
                    ->each(fn (DocumentBackground $old) => $old->forceFill(['retired_at' => now(), 'retired_by' => $actor->id])->save());
                $bg = new DocumentBackground(['kind' => $kind, 'margin_top' => $margins['top'], 'margin_bottom' => $margins['bottom'], 'margin_side' => $margins['side']]);
                $bg->id = $id;
                $bg->forceFill([
                    'source_name' => $original, 'source_mime' => $type['mime'], 'source_path' => $sourcePath, 'source_sha256' => hash('sha256', $bytes),
                    'source_bytes' => strlen($bytes), 'render_path' => $renderPath, 'render_sha256' => hash('sha256', $render),
                    'conversion_notes' => $notes === [] ? null : implode("\n", $notes), 'uploaded_by' => $actor->id, 'uploaded_at' => now(),
                ])->save();

                return $bg;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete([$sourcePath, $renderPath]);

            throw $e;
        }
        $this->audit->record('document_background.uploaded', $actor->id, 'document_background', $bg->id, ['kind' => $kind, 'mime' => $type['mime']]);

        return $bg;
    }

    /** @param array{top: int, bottom: int, side: int} $margins */
    public function updateMargins(DocumentBackground $bg, array $margins, User $actor): void
    {
        abort_if($bg->retired_at !== null, 404);
        $bg->fill(['margin_top' => $margins['top'], 'margin_bottom' => $margins['bottom'], 'margin_side' => $margins['side']])->save();
        $this->audit->record('document_background.margins_updated', $actor->id, 'document_background', $bg->id, $margins);
    }

    /** Remove o fundo do uso (aposenta; arquivos e histórico ficam). */
    public function retire(DocumentBackground $bg, User $actor): void
    {
        if ($bg->retired_at !== null) {
            return;
        }
        $bg->forceFill(['retired_at' => now(), 'retired_by' => $actor->id])->save();
        $this->audit->record('document_background.retired', $actor->id, 'document_background', $bg->id);
    }

    /** PDF do fundo pronto, conferindo a integridade. */
    public function renderBytes(DocumentBackground $bg): string
    {
        $bytes = Storage::disk('local')->get($bg->render_path);
        if ($bytes === null || ! hash_equals($bg->render_sha256, hash('sha256', $bytes))) {
            abort(500, 'Arquivo do fundo ausente ou alterado.');
        }

        return $bytes;
    }

    public function originalBytes(DocumentBackground $bg): string
    {
        $bytes = Storage::disk('local')->get($bg->source_path);
        if ($bytes === null || ! hash_equals($bg->source_sha256, hash('sha256', $bytes))) {
            abort(500, 'Original do fundo ausente ou alterado.');
        }

        return $bytes;
    }

    /**
     * @return array{0: string, 1: array{top: int, bottom: int, side: int}, 2: list<string>}
     */
    private function prepare(string $bytes, string $extension): array
    {
        $margins = DocumentBackground::DEFAULT_MARGINS;
        $notes = [];
        try {
            switch ($extension) {
                case 'pdf':
                    if (preg_match('#/Encrypt\b#', $bytes)) {
                        throw new \RuntimeException('O PDF está protegido por senha/restrições. Gere o PDF sem proteção.');
                    }
                    $render = PdfXrefFlattener::needsFlattening($bytes) ? PdfXrefFlattener::flatten($bytes) : $bytes;
                    if (preg_match_all('#/Type\s*/Page\b#', $bytes) > 1) {
                        $notes[] = 'O PDF tem mais de uma página: usamos só a primeira como fundo.';
                    }
                    break;
                case 'docx':
                    $docx = DocxLetterhead::convert($bytes);
                    $render = PdfRenderer::html($docx['html']);
                    $margins = $docx['margins'];
                    $notes = $docx['notes'];
                    break;
                default: // jpg/png: página inteira
                    $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
                    $render = PdfRenderer::html('<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{margin:0}body{margin:0}</style></head><body>'
                        .'<img src="data:'.$mime.';base64,'.base64_encode($bytes).'" style="position:absolute;left:0;top:0;width:210mm;height:297mm" alt=""></body></html>');
                    $notes[] = 'A imagem foi esticada para a página A4 inteira. Para melhor qualidade de impressão, use pelo menos 1240 × 1754 pixels (150 dpi).';
            }
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Não foi possível preparar este arquivo como fundo. Tente exportá-lo novamente em PDF.']);
        }
        if (! PdfOverlay::readable($render)) {
            throw ValidationException::withMessages(['file' => 'Este PDF usa um recurso que ainda não conseguimos ler. Tente “Imprimir → Salvar como PDF” e envie de novo.']);
        }

        return [$render, $margins, $notes];
    }
}
