import { describe, expect, it } from 'vitest';
import {
  contrastRatio,
  dashboardFor,
  hasPermission,
  isValidCnpj,
  isValidCpf,
  onboardingRequestSchema,
  permissionsFor,
  updateThemeRequestSchema,
} from './index';

describe('permissões (negar por padrão)', () => {
  it('recepção não tem acesso clínico', () => {
    expect(hasPermission(['receptionist'], 'clinical:read')).toBe(false);
  });
  it('veterinário tem leitura clínica mas não altera tema', () => {
    expect(hasPermission(['veterinarian'], 'clinical:read')).toBe(true);
    expect(hasPermission(['veterinarian'], 'theme:update')).toBe(false);
  });
  it('papel desconhecido não concede nada', () => {
    expect(permissionsFor(['platform_admin', 'hacker'])).toEqual([]);
  });
  it('financeiro e estoque não acessam prontuário', () => {
    for (const r of ['finance', 'stock', 'pet_services']) {
      expect(hasPermission([r], 'clinical:read')).toBe(false);
    }
  });
  it('dashboard por função', () => {
    expect(dashboardFor(['tenant_owner'])).toBe('management');
    expect(dashboardFor(['veterinarian'])).toBe('clinical');
    expect(dashboardFor(['receptionist'])).toBe('reception');
    expect(dashboardFor(['finance'])).toBe('finance');
    expect(dashboardFor(['stock'])).toBe('operations');
  });
});

describe('documentos', () => {
  it('valida CPF/CNPJ fictícios gerados por dígito verificador', () => {
    expect(isValidCpf('529.982.247-25')).toBe(true);
    expect(isValidCpf('111.111.111-11')).toBe(false);
    expect(isValidCpf('529.982.247-24')).toBe(false);
    expect(isValidCnpj('11.222.333/0001-81')).toBe(true);
    expect(isValidCnpj('11.222.333/0001-80')).toBe(false);
  });
  it('onboarding rejeita documento inválido', () => {
    const r = onboardingRequestSchema.safeParse({
      personType: 'PJ',
      tradeName: 'Clínica Teste',
      document: '00.000.000/0000-00',
      ownerName: 'Pessoa Teste',
      email: 'a@exemplo.test',
      password: 'senha-longa-de-teste',
      unitName: 'Matriz',
    });
    expect(r.success).toBe(false);
  });
  it('onboarding normaliza e-mail', () => {
    const r = onboardingRequestSchema.parse({
      personType: 'PF',
      tradeName: 'Dra. Fictícia',
      ownerName: 'Dra. Fictícia',
      email: '  DRA@Exemplo.TEST ',
      password: 'senha-longa-de-teste',
      unitName: 'Atendimento domiciliar',
    });
    expect(r.email).toBe('dra@exemplo.test');
    expect(r.timezone).toBe('America/Sao_Paulo');
  });
});

describe('tema e contraste', () => {
  it('calcula contraste preto/branco = 21', () => {
    expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 1);
  });
  it('aceita cores com contraste AA', () => {
    expect(
      updateThemeRequestSchema.safeParse({
        primary: '#0e5e66',
        secondary: '#7a4fa3',
        mode: 'light',
      }).success,
    ).toBe(true);
  });
  it('rejeita cor de meio-tom sem contraste AA', () => {
    const r = updateThemeRequestSchema.safeParse({
      primary: '#808080',
      secondary: '#7a4fa3',
      mode: 'light',
    });
    expect(r.success).toBe(false);
  });
  it('rejeita formato não hexadecimal (evita injeção de CSS)', () => {
    const r = updateThemeRequestSchema.safeParse({
      primary: 'red;}body{',
      secondary: '#7a4fa3',
      mode: 'light',
    });
    expect(r.success).toBe(false);
  });
});
