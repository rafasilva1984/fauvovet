<?php

namespace App\Documents;

use App\Files\FileTypeDetector;
use App\Files\TextExtractor;
use App\Models\DocumentTemplate;
use App\Support\SearchText;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Importa um documento que a clínica já usa (DOCX/PDF) como MODELO (CLAUDE.md §4.2; ADR-019):
 *  1. confere o tipo pelo conteúdo e o tamanho; 2. guarda o original em disco privado com SHA-256;
 *  3. extrai o texto; 4. sugere variáveis para rótulos conhecidos seguidos de lacuna ("Paciente: ______");
 *  5. cria o modelo DESATIVADO, para revisão humana antes de usar.
 * Formatação (negrito, tabelas, imagens, timbre) não é convertida — a tela diz isso claramente.
 */
final class TemplateImporter
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Rótulo (sem acento, minúsculo) → variável da whitelist. */
    private const LABELS = [
        'paciente' => 'paciente.nome', 'nome do paciente' => 'paciente.nome', 'nome do animal' => 'paciente.nome', 'animal' => 'paciente.nome',
        'especie' => 'paciente.especie', 'raca' => 'paciente.raca', 'sexo' => 'paciente.sexo', 'idade' => 'paciente.idade',
        'peso' => 'paciente.peso_atual', 'microchip' => 'paciente.microchip',
        'tutor' => 'responsavel.nome', 'responsavel' => 'responsavel.nome', 'proprietario' => 'responsavel.nome', 'nome do tutor' => 'responsavel.nome',
        'nome do responsavel' => 'responsavel.nome', 'cpf' => 'responsavel.documento', 'cpf do tutor' => 'responsavel.documento',
        'cpf do responsavel' => 'responsavel.documento', 'telefone' => 'responsavel.telefone', 'endereco' => 'responsavel.endereco',
        'medico veterinario' => 'profissional.nome', 'medica veterinaria' => 'profissional.nome', 'veterinario' => 'profissional.nome',
        'veterinaria' => 'profissional.nome', 'crmv' => 'profissional.crmv', 'data' => 'data.hoje', 'clinica' => 'clinica.nome',
    ];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array{template: DocumentTemplate, suggestions: list<string>}
     */
    public function import(UploadedFile $file, string $category, string $name, string $userId): array
    {
        $original = FileTypeDetector::safeName($file->getClientOriginalName());
        $type = FileTypeDetector::detect((string) $file->getRealPath(), $original);
        if ($type === null || ! in_array($type['extension'], ['pdf', 'docx'], true)) {
            throw ValidationException::withMessages(['file' => 'Envie um arquivo Word (DOCX) ou PDF. O conteúdo precisa corresponder à extensão.']);
        }
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Arquivo acima de 10 MB.']);
        }
        $text = TextExtractor::extract($bytes, $type['extension']);
        if ($text['status'] !== 'indexed') {
            throw ValidationException::withMessages(['file' => 'Não foi possível converter: o arquivo não tem texto legível (PDF digitalizado ou protegido?). Digite o modelo em “Novo modelo”.']);
        }
        [$body, $suggestions] = self::convert((string) $text['text']);

        return $this->create($bytes, $type, $original, $category, $name, $body, $userId) + ['suggestions' => $suggestions];
    }

    /**
     * Cria o modelo a partir de bytes já validados (usado também pela demonstração).
     *
     * @param  array{mime: string, extension: string}  $type
     * @return array{template: DocumentTemplate}
     */
    public function create(string $bytes, array $type, string $original, string $category, string $name, string $body, string $userId, ?string $id = null, ?string $title = null): array
    {
        $id ??= (string) Str::ulid();
        $path = sprintf('templates/%s/%s.%s', $this->context->requireId(), $id, $type['extension']);
        Storage::disk('local')->put($path, $bytes);
        try {
            $template = new DocumentTemplate([
                'category' => $category, 'name' => $name, 'title' => mb_substr($title ?? self::firstLine($body) ?? pathinfo($original, PATHINFO_FILENAME), 0, 160) ?: $name,
                'body' => $body, 'version' => 1, 'active' => false, 'updated_by' => $userId,
            ]);
            $template->id = $id;
            $template->forceFill(['source_name' => $original, 'source_mime' => $type['mime'], 'source_path' => $path,
                'source_sha256' => hash('sha256', $bytes), 'source_bytes' => strlen($bytes), 'imported_at' => now(), 'imported_by' => $userId]);
            $template->save();
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        return ['template' => $template];
    }

    /** Primeira linha com texto (sem variáveis/lacunas) — vira o título sugerido. */
    public static function firstLine(string $body): ?string
    {
        foreach (explode("\n", $body) as $line) {
            $line = trim((string) preg_replace('/\{\{[^}]*\}\}|\[completar\]/', '', $line), " \t:");
            if (mb_strlen($line) >= 3) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Texto extraído → corpo do modelo. "Rótulo: ____" vira "Rótulo: {{variável}}" quando o rótulo é conhecido;
     * outras lacunas viram "[completar]" (lembrete na emissão). Chaves soltas "{{"/"}}" do original são neutralizadas.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function convert(string $text): array
    {
        $suggestions = [];
        $text = str_replace(['{{', '}}'], ['{ {', '} }'], $text);
        $text = (string) preg_replace_callback('/([\p{L} ]{2,40}):[ \t]*(?:_{2,}[ \t]*\/[ \t]*_{2,}[ \t]*\/[ \t]*_{2,}|_{3,}|\.{4,}|…+)/u', function (array $m) use (&$suggestions) {
            $label = trim($m[1]);
            // Rótulo pode vir no fim da frase ("Eu, tutor do Paciente: ___"): tenta as 3, 2 e 1 últimas palavras.
            $words = explode(' ', trim((string) preg_replace('/\s+/', ' ', SearchText::normalize($label))));
            $key = null;
            for ($k = min(3, count($words)); $k >= 1 && $key === null; $k--) {
                $key = self::LABELS[implode(' ', array_slice($words, -$k))] ?? null;
            }
            if ($key === null) {
                return rtrim($m[1]).': [completar]';
            }
            $suggestions[] = $label.' → {{'.$key.'}}';

            return rtrim($m[1]).': {{'.$key.'}}'; // mantém o espaço antes do rótulo ("Espécie: x  Raça: y")
        }, $text);
        $text = (string) preg_replace('/_{3,}|\.{6,}/u', '[completar]', $text);

        return [mb_substr(trim($text), 0, 20000), array_values(array_unique($suggestions))];
    }
}
