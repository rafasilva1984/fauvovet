<?php

namespace App\Tenancy;

/**
 * Papéis e permissões (CLAUDE.md §5.2). Negar por padrão: uma permissão só existe se listada aqui.
 */
final class Roles
{
    public const LABELS = [
        'tenant_owner' => 'Titular',
        'tenant_manager' => 'Gestão',
        'veterinarian' => 'Médico-veterinário',
        'clinical_assistant' => 'Assistente clínico',
        'receptionist' => 'Recepção',
        'finance' => 'Financeiro',
        'stock' => 'Estoque',
        'pet_services' => 'Banho e tosa',
    ];

    /** Papéis administrativos: só o titular pode conceder, retirar ou editar quem os possui. */
    public const ADMIN_ROLES = ['tenant_owner', 'tenant_manager'];

    private const BASE = ['tenant:read', 'units:read'];

    private const MATRIX = [
        'tenant_owner' => ['tenant:update', 'theme:update', 'units:create', 'members:read', 'members:manage', 'audit:read', 'patients:read', 'patients:write', 'schedule:read', 'schedule:write', 'schedule:attend', 'schedule:configure', 'clinical:read', 'clinical:write', 'documents:templates', 'vaccines:read', 'vaccines:write', 'vaccines:configure', 'research:manage'],
        'tenant_manager' => ['units:create', 'members:read', 'members:manage', 'audit:read', 'patients:read', 'patients:write', 'schedule:read', 'schedule:write', 'schedule:configure', 'documents:templates', 'vaccines:read', 'vaccines:configure'],
        'veterinarian' => ['patients:read', 'patients:write', 'schedule:read', 'schedule:write', 'schedule:attend', 'clinical:read', 'clinical:write', 'clinical:sign', 'documents:templates', 'vaccines:read', 'vaccines:write', 'vaccines:configure', 'research:manage'],
        'clinical_assistant' => ['patients:read', 'patients:write', 'schedule:read', 'schedule:write', 'schedule:attend', 'clinical:read', 'clinical:write', 'vaccines:read', 'vaccines:write'],
        'receptionist' => ['patients:read', 'patients:write', 'schedule:read', 'schedule:write', 'vaccines:read'],
        'finance' => ['patients:read', 'schedule:read'],
        'stock' => [],
        'pet_services' => ['patients:read', 'schedule:read', 'schedule:write', 'schedule:attend'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function isRole(mixed $role): bool
    {
        return is_string($role) && array_key_exists($role, self::LABELS);
    }

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? $role;
    }

    /**
     * @param  array<int, mixed>  $roles
     * @return list<string>
     */
    public static function permissions(array $roles): array
    {
        $set = [];
        foreach ($roles as $role) {
            if (! self::isRole($role)) {
                continue; // papel desconhecido não concede nada
            }
            foreach ([...self::BASE, ...self::MATRIX[$role]] as $p) {
                $set[$p] = true;
            }
        }
        $list = array_keys($set);
        sort($list);

        return $list;
    }

    /** @param  array<int, mixed>  $roles */
    public static function has(array $roles, string $permission): bool
    {
        return in_array($permission, self::permissions($roles), true);
    }

    /**
     * Painel inicial por função (CLAUDE.md §7.1).
     *
     * @param  array<int, mixed>  $roles
     */
    public static function dashboard(array $roles): string
    {
        return match (true) {
            in_array('tenant_owner', $roles, true), in_array('tenant_manager', $roles, true) => 'management',
            in_array('veterinarian', $roles, true), in_array('clinical_assistant', $roles, true) => 'clinical',
            in_array('receptionist', $roles, true) => 'reception',
            in_array('finance', $roles, true) => 'finance',
            default => 'operations',
        };
    }
}
