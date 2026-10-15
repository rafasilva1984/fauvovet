/** Utilitários de contraste WCAG 2.x para validar temas por tenant (CLAUDE.md §4.1). */
export const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

function channel(c: number): number {
  const s = c / 255;
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
}

export function relativeLuminance(hex: string): number {
  if (!HEX_COLOR.test(hex)) throw new Error(`Cor inválida: ${hex}`);
  const n = parseInt(hex.slice(1), 16);
  const r = (n >> 16) & 0xff;
  const g = (n >> 8) & 0xff;
  const b = n & 0xff;
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function contrastRatio(a: string, b: string): number {
  const la = relativeLuminance(a);
  const lb = relativeLuminance(b);
  const [hi, lo] = la > lb ? [la, lb] : [lb, la];
  return (hi + 0.05) / (lo + 0.05);
}

/** Cor de texto (branco ou quase-preto) com maior contraste sobre o fundo informado. */
export function bestTextOn(background: string): '#ffffff' | '#0f1e24' {
  return contrastRatio(background, '#ffffff') >= contrastRatio(background, '#0f1e24')
    ? '#ffffff'
    : '#0f1e24';
}

/** WCAG AA para texto normal exige 4.5:1. */
export const WCAG_AA_NORMAL = 4.5;
