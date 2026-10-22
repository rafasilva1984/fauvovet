<?php

namespace App\Support;

/**
 * Mapa de navegação único (menu lateral, barra do celular, menu "Mais" e página Configurações), filtrado pelas
 * permissões de quem está logado. Mudou um item de lugar? Muda só aqui.
 *
 * Cada item: route, label, icon, perm e, opcionalmente, active (padrões extras de rota que o destacam) e hint
 * (descrição curta, usada na página Configurações).
 */
final class Navigation
{
    /** @var array<string, list<array{route: string, label: string, icon: string, perm: string, active?: list<string>}>> */
    private const MAIN = [
        'Atendimento' => [
            ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'home', 'perm' => 'tenant:read'],
            ['route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'calendar', 'perm' => 'schedule:read'],
            ['route' => 'patients.index', 'label' => 'Pacientes', 'icon' => 'paw', 'perm' => 'patients:read', 'active' => ['patients.*', 'encounters.*', 'documents.*', 'prescriptions.*']],
            ['route' => 'guardians.index', 'label' => 'Responsáveis', 'icon' => 'users', 'perm' => 'patients:read'],
            ['route' => 'search.index', 'label' => 'Busca clínica', 'icon' => 'search', 'perm' => 'clinical:read'],
        ],
        'Acompanhamento' => [
            ['route' => 'exams.index', 'label' => 'Exames', 'icon' => 'flask', 'perm' => 'clinical:read'],
            ['route' => 'vaccinations.index', 'label' => 'Vacinas a vencer', 'icon' => 'syringe', 'perm' => 'vaccines:read'],
            ['route' => 'cases.index', 'label' => 'Casos científicos', 'icon' => 'file-text', 'perm' => 'research:manage'],
        ],
        'Gestão' => [
            ['route' => 'team.index', 'label' => 'Equipe', 'icon' => 'shield', 'perm' => 'members:read'],
        ],
    ];

    /** @var array<string, list<array{route: string, label: string, icon: string, perm: string, hint: string, active?: list<string>, demo?: bool}>> */
    private const SETTINGS = [
        'Agenda' => [
            ['route' => 'settings.appointment-types', 'label' => 'Tipos de atendimento', 'icon' => 'list', 'perm' => 'schedule:configure', 'hint' => 'Consulta, retorno, vacina, banho e tosa: nome, duração e cor na agenda.', 'active' => ['settings.appointment-types*']],
        ],
        'Catálogos clínicos' => [
            ['route' => 'exam-types.index', 'label' => 'Catálogo de exames', 'icon' => 'flask', 'perm' => 'exams:configure', 'hint' => 'Exames que a clínica pede, parâmetros, unidades e faixas de referência por espécie.', 'active' => ['exam-types.*', 'exam-analytes.*', 'exam-ranges.*']],
            ['route' => 'medications.index', 'label' => 'Catálogo de medicamentos', 'icon' => 'pill', 'perm' => 'medications:configure', 'hint' => 'Medicamentos, concentrações e doses por kg usados na receita estruturada; faixas para avisar dose fora do padrão.', 'active' => ['medications.*']],
            ['route' => 'vaccines.index', 'label' => 'Catálogo de vacinas', 'icon' => 'syringe', 'perm' => 'vaccines:configure', 'hint' => 'Vacinas, espécies, número de doses e intervalo dos reforços.', 'active' => ['vaccines.*']],
            ['route' => 'search.synonyms', 'label' => 'Sinônimos da busca', 'icon' => 'search', 'perm' => 'search:configure', 'hint' => 'Termos equivalentes para a busca clínica, como “DRC” e “doença renal crônica”.', 'active' => ['search.synonyms*']],
        ],
        'Documentos' => [
            ['route' => 'document-templates.index', 'label' => 'Modelos de documento', 'icon' => 'copy', 'perm' => 'documents:templates', 'hint' => 'Receitas, atestados e termos com variáveis do paciente; importe os seus em DOCX ou PDF.', 'active' => ['document-templates.*']],
            ['route' => 'document-backgrounds.index', 'label' => 'Papel timbrado e receituário', 'icon' => 'palette', 'perm' => 'documents:templates', 'hint' => 'Fundos usados nos PDFs emitidos. Envie em PDF, Word ou imagem.', 'active' => ['document-backgrounds.*']],
        ],
        'Clínica' => [
            ['route' => 'units.index', 'label' => 'Unidades', 'icon' => 'building', 'perm' => 'units:create', 'hint' => 'Endereços, fuso horário e filiais da clínica.', 'active' => ['units.*']],
            ['route' => 'settings.appearance', 'label' => 'Aparência', 'icon' => 'palette', 'perm' => 'theme:update', 'hint' => 'Cores da clínica no sistema e nos documentos.', 'active' => ['settings.appearance*']],
            ['route' => 'settings.demo-data', 'label' => 'Dados de demonstração', 'icon' => 'sparkles', 'perm' => 'tenant:update', 'hint' => 'Pacientes e atendimentos fictícios para conhecer o sistema; remova antes de usar com dados reais.', 'active' => ['settings.demo-data*'], 'demo' => true],
        ],
    ];

    /** Ordem de preferência da barra inferior do celular (4 espaços + botão Menu). */
    private const MOBILE = ['dashboard', 'agenda.index', 'patients.index', 'search.index', 'vaccinations.index', 'guardians.index'];

    /**
     * @param  callable(string): bool  $can
     * @return array<string, list<array<string, mixed>>>
     */
    public static function main(callable $can): array
    {
        $groups = self::filter(self::MAIN, $can);
        if (self::settings($can) !== []) {
            $groups['Gestão'][] = ['route' => 'settings.index', 'label' => 'Configurações', 'icon' => 'settings', 'perm' => 'tenant:read', 'active' => self::settingsPatterns($can)];
        }

        return $groups;
    }

    /**
     * @param  callable(string): bool  $can
     * @return array<string, list<array<string, mixed>>>
     */
    public static function settings(callable $can): array
    {
        return self::filter(self::SETTINGS, fn (string $perm) => $can($perm));
    }

    /**
     * @param  callable(string): bool  $can
     * @return list<array<string, mixed>>
     */
    public static function mobile(callable $can): array
    {
        $byRoute = [];
        foreach (self::main($can) as $items) {
            foreach ($items as $item) {
                $byRoute[$item['route']] = $item;
            }
        }
        $picked = [];
        foreach (self::MOBILE as $route) {
            if (isset($byRoute[$route]) && count($picked) < 4) {
                $picked[] = $byRoute[$route];
            }
        }

        return $picked;
    }

    /** @param array<string, mixed> $item */
    public static function isActive(array $item): bool
    {
        $patterns = [$item['route'], preg_replace('/\.index$/', '', $item['route']).'.*', ...($item['active'] ?? [])];

        return request()->routeIs(...$patterns);
    }

    /**
     * Página atual é uma tela de configuração (para o link "Configurações" de volta)?
     *
     * @param  callable(string): bool  $can
     */
    public static function inSettings(callable $can): bool
    {
        $patterns = self::settingsPatterns($can);

        return $patterns !== [] && request()->routeIs(...$patterns) && ! request()->routeIs('settings.index');
    }

    /**
     * @param  callable(string): bool  $can
     * @return list<string>
     */
    private static function settingsPatterns(callable $can): array
    {
        $patterns = [];
        foreach (self::settings($can) as $items) {
            foreach ($items as $item) {
                array_push($patterns, $item['route'], ...($item['active'] ?? []));
            }
        }

        return $patterns;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @param  callable(string): bool  $can
     * @return array<string, list<array<string, mixed>>>
     */
    private static function filter(array $groups, callable $can): array
    {
        $out = [];
        foreach ($groups as $label => $items) {
            $items = array_values(array_filter($items, fn (array $i) => $can($i['perm']) && (! ($i['demo'] ?? false) || config('fauvovet.demo_data'))));
            if ($items !== []) {
                $out[$label] = $items;
            }
        }

        return $out;
    }
}
