import { DEFAULT_THEME, HEX_COLOR, ThemeTokens, bestTextOn } from '@fauvovet/contracts';

/** Aplica os tokens do tenant em :root. Cores inválidas são ignoradas (fallback ao padrão). */
export function applyTheme(theme: ThemeTokens | null | undefined) {
  const t = theme ?? DEFAULT_THEME;
  const root = document.documentElement;
  const primary = HEX_COLOR.test(t.primary) ? t.primary : DEFAULT_THEME.primary;
  const secondary = HEX_COLOR.test(t.secondary) ? t.secondary : DEFAULT_THEME.secondary;
  root.style.setProperty('--fv-primary', primary);
  root.style.setProperty('--fv-on-primary', bestTextOn(primary));
  root.style.setProperty('--fv-secondary', secondary);
  root.style.setProperty('--fv-on-secondary', bestTextOn(secondary));
  if (t.mode === 'system') root.removeAttribute('data-theme');
  else root.setAttribute('data-theme', t.mode);
}

export function resetTheme() {
  const root = document.documentElement;
  for (const p of ['--fv-primary', '--fv-on-primary', '--fv-secondary', '--fv-on-secondary'])
    root.style.removeProperty(p);
  root.removeAttribute('data-theme');
}
