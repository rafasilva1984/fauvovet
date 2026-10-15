// Contraste WCAG — espelho de App\Support\Contrast (o servidor valida de novo ao salvar).
export const HEX = /^#[0-9a-fA-F]{6}$/;
export const AA_NORMAL = 4.5;

const channel = (c) => {
  const s = c / 255;
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
};

export function luminance(hex) {
  const n = parseInt(hex.slice(1), 16);
  return 0.2126 * channel((n >> 16) & 0xff) + 0.7152 * channel((n >> 8) & 0xff) + 0.0722 * channel(n & 0xff);
}

export function ratio(a, b) {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

export const textOn = (bg) => (ratio(bg, '#ffffff') >= ratio(bg, '#0f1e24') ? '#ffffff' : '#0f1e24');

/** null quando a cor é inválida. */
export const contrastOf = (hex) => (HEX.test(hex) ? ratio(hex, textOn(hex)) : null);
