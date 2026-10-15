/**
 * Papéis e permissões iniciais (CLAUDE.md §5.2).
 * Regra: negar por padrão — uma permissão só existe para um papel se listada aqui.
 * A verificação efetiva acontece no backend (guards da API) e o banco aplica RLS por tenant.
 */
export const TENANT_ROLES = [
  'tenant_owner',
  'tenant_manager',
  'veterinarian',
  'clinical_assistant',
  'receptionist',
  'finance',
  'stock',
  'pet_services',
] as const;

export type TenantRole = (typeof TENANT_ROLES)[number];

export const ROLE_LABELS: Record<TenantRole, string> = {
  tenant_owner: 'Titular',
  tenant_manager: 'Gestão',
  veterinarian: 'Médico-veterinário',
  clinical_assistant: 'Assistente clínico',
  receptionist: 'Recepção',
  finance: 'Financeiro',
  stock: 'Estoque',
  pet_services: 'Banho e tosa',
};

export const PERMISSIONS = [
  'tenant:read',
  'tenant:update',
  'theme:update',
  'units:read',
  'units:create',
  'members:read',
  'audit:read',
  'clinical:read',
  'clinical:write',
] as const;

export type Permission = (typeof PERMISSIONS)[number];

const ALL_STAFF: readonly Permission[] = ['tenant:read', 'units:read'];

export const ROLE_PERMISSIONS: Record<TenantRole, readonly Permission[]> = {
  tenant_owner: [
    ...ALL_STAFF,
    'tenant:update',
    'theme:update',
    'units:create',
    'members:read',
    'audit:read',
    'clinical:read',
    'clinical:write',
  ],
  tenant_manager: [...ALL_STAFF, 'units:create', 'members:read', 'audit:read'],
  veterinarian: [...ALL_STAFF, 'clinical:read', 'clinical:write'],
  clinical_assistant: [...ALL_STAFF, 'clinical:read'],
  receptionist: [...ALL_STAFF],
  finance: [...ALL_STAFF],
  stock: [...ALL_STAFF],
  pet_services: [...ALL_STAFF],
};

export function isTenantRole(value: unknown): value is TenantRole {
  return typeof value === 'string' && (TENANT_ROLES as readonly string[]).includes(value);
}

export function permissionsFor(roles: readonly string[]): Permission[] {
  const set = new Set<Permission>();
  for (const role of roles) {
    if (!isTenantRole(role)) continue; // papel desconhecido não concede nada
    for (const p of ROLE_PERMISSIONS[role]) set.add(p);
  }
  return [...set].sort();
}

export function hasPermission(roles: readonly string[], permission: Permission): boolean {
  return permissionsFor(roles).includes(permission);
}

/** Painel inicial por função (CLAUDE.md §7.1). */
export type DashboardKind = 'management' | 'clinical' | 'reception' | 'finance' | 'operations';

export function dashboardFor(roles: readonly string[]): DashboardKind {
  if (roles.includes('tenant_owner') || roles.includes('tenant_manager')) return 'management';
  if (roles.includes('veterinarian') || roles.includes('clinical_assistant')) return 'clinical';
  if (roles.includes('receptionist')) return 'reception';
  if (roles.includes('finance')) return 'finance';
  return 'operations';
}
