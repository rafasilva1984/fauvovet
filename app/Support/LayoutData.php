<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantTheme;
use App\Models\Unit;
use App\Tenancy\Roles;
use App\Tenancy\TenantContext;

/**
 * Dados compartilhados pelas telas autenticadas, calculados uma vez POR REQUISIÇÃO
 * (registrado como scoped — nunca usar cache estático aqui: o processo PHP atende várias clínicas).
 */
final class LayoutData
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    private ?string $dataKey = null;

    public function __construct(private readonly TenantContext $context) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $context = $this->context;
        // Memo amarrado à clínica+vínculo+unidade: nunca reaproveita dados calculados para outro contexto.
        $key = implode('|', [$context->id(), $context->membership()?->id, $context->unitId()]);
        if ($this->data !== null && $this->dataKey === $key) {
            return $this->data;
        }
        $this->dataKey = $key;
        $tenant = $context->id() ? Tenant::query()->with('theme')->first() : null;
        $theme = $tenant?->theme;

        return $this->data = [
            'fvTenant' => $tenant,
            'fvTheme' => [
                'primary' => $theme->primary_color ?? TenantTheme::DEFAULT['primary'],
                'secondary' => $theme->secondary_color ?? TenantTheme::DEFAULT['secondary'],
                'mode' => $theme->mode ?? TenantTheme::DEFAULT['mode'],
            ],
            'fvUnitName' => $context->unitId() ? Unit::query()->whereKey($context->unitId())->value('name') : null,
            'fvRoles' => array_map([Roles::class, 'label'], $context->roles()),
            'fvCan' => fn (string $p): bool => $context->can($p),
        ];
    }
}
