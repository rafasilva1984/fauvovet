import { z } from 'zod';
import { HEX_COLOR, WCAG_AA_NORMAL, bestTextOn, contrastRatio } from './color';
import { isValidCnpj, isValidCpf, onlyDigits } from './documents';
import { TENANT_ROLES } from './roles';

const email = z
  .string()
  .trim()
  .toLowerCase()
  .max(254)
  .pipe(z.email({ message: 'Informe um e-mail válido.' }));

export const passwordSchema = z
  .string()
  .min(12, 'A senha deve ter ao menos 12 caracteres.')
  .max(128, 'A senha deve ter no máximo 128 caracteres.');

export const loginRequestSchema = z.object({
  email,
  password: z.string().min(1, 'Informe a senha.').max(128),
});
export type LoginRequest = z.infer<typeof loginRequestSchema>;

export const selectContextRequestSchema = z.object({
  membershipId: z.uuid(),
  unitId: z.uuid().optional(),
});
export type SelectContextRequest = z.infer<typeof selectContextRequestSchema>;

/** Fusos válidos conforme o runtime (IANA). */
export const timezoneSchema = z.string().refine((tz) => {
  try {
    new Intl.DateTimeFormat('pt-BR', { timeZone: tz });
    return true;
  } catch {
    return false;
  }
}, 'Fuso horário inválido.');

export const onboardingRequestSchema = z
  .object({
    personType: z.enum(['PF', 'PJ']),
    tradeName: z.string().trim().min(2, 'Informe o nome da clínica/profissional.').max(120),
    legalName: z.string().trim().max(160).optional(),
    document: z.string().trim().max(20).optional(),
    ownerName: z.string().trim().min(2, 'Informe seu nome.').max(120),
    ownerCrmv: z.string().trim().max(30).optional(),
    email,
    password: passwordSchema,
    unitName: z.string().trim().min(2, 'Informe o nome da unidade.').max(120),
    timezone: timezoneSchema.default('America/Sao_Paulo'),
  })
  .superRefine((value, ctx) => {
    if (!value.document) return;
    const digits = onlyDigits(value.document);
    const ok = value.personType === 'PF' ? isValidCpf(digits) : isValidCnpj(digits);
    if (!ok) {
      ctx.addIssue({
        code: 'custom',
        path: ['document'],
        message: value.personType === 'PF' ? 'CPF inválido.' : 'CNPJ inválido.',
      });
    }
  });
export type OnboardingRequest = z.infer<typeof onboardingRequestSchema>;

export const createUnitRequestSchema = z.object({
  name: z.string().trim().min(2, 'Informe o nome da unidade.').max(120),
  timezone: timezoneSchema.default('America/Sao_Paulo'),
  addressLine: z.string().trim().max(200).optional(),
  city: z.string().trim().max(80).optional(),
  state: z
    .string()
    .trim()
    .regex(/^[A-Za-z]{2}$/, 'UF deve ter 2 letras.')
    .transform((s) => s.toUpperCase())
    .optional(),
});
export type CreateUnitRequest = z.infer<typeof createUnitRequestSchema>;

export const themeTokensSchema = z.object({
  primary: z.string().regex(HEX_COLOR, 'Use o formato #RRGGBB.'),
  secondary: z.string().regex(HEX_COLOR, 'Use o formato #RRGGBB.'),
  mode: z.enum(['light', 'dark', 'system']),
});
export type ThemeTokens = z.infer<typeof themeTokensSchema>;

/** Cores aplicadas sobre fundo claro como texto/realce precisam de AA. */
export const updateThemeRequestSchema = themeTokensSchema.superRefine((value, ctx) => {
  for (const key of ['primary', 'secondary'] as const) {
    const color = value[key];
    if (!HEX_COLOR.test(color)) continue; // erro de formato já reportado pelo schema base
    const onColor = bestTextOn(color);
    if (contrastRatio(color, onColor) < WCAG_AA_NORMAL) {
      ctx.addIssue({
        code: 'custom',
        path: [key],
        message: `Contraste insuficiente para texto sobre esta cor (mínimo ${WCAG_AA_NORMAL}:1). Escolha um tom mais escuro ou mais claro.`,
      });
    }
  }
});

export const DEFAULT_THEME: ThemeTokens = {
  primary: '#0e5e66',
  secondary: '#7a4fa3',
  mode: 'system',
};

export const tenantRoleSchema = z.enum(TENANT_ROLES);

// ---- Respostas públicas da API (contratos tipados) ----

export interface MembershipSummary {
  membershipId: string;
  tenantId: string;
  tenantName: string;
  roles: string[];
  units: { id: string; name: string }[];
}

export interface ActiveContext {
  tenantId: string;
  tenantName: string;
  membershipId: string;
  unitId: string | null;
  roles: string[];
  permissions: string[];
  dashboard: string;
}

export interface MeResponse {
  user: { id: string; name: string; email: string };
  memberships: MembershipSummary[];
  context: ActiveContext | null;
  csrfToken: string;
}

export interface UnitDto {
  id: string;
  name: string;
  timezone: string;
  addressLine: string | null;
  city: string | null;
  state: string | null;
  createdAt: string;
}

export interface TenantDto {
  id: string;
  tradeName: string;
  personType: 'PF' | 'PJ';
  theme: ThemeTokens;
}

export interface MemberDto {
  membershipId: string;
  userId: string;
  name: string;
  email: string;
  roles: string[];
  status: string;
}

export interface ApiError {
  error: { code: string; message: string; details?: unknown; requestId?: string };
}
