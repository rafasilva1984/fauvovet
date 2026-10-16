// Alpine no build CSP (sem eval): componentes registrados aqui; a CSP da aplicação não permite 'unsafe-eval'.
import Alpine from '@alpinejs/csp';
import { AA_NORMAL, HEX, contrastOf, textOn } from './contrast';

Alpine.data('disclosure', () => ({
  open: false,
  toggle() {
    this.open = !this.open;
  },
  close() {
    this.open = false;
  },
}));

Alpine.data('passwordField', () => ({
  visible: false,
  toggle() {
    this.visible = !this.visible;
  },
  get type() {
    return this.visible ? 'text' : 'password';
  },
  get label() {
    return this.visible ? 'Ocultar senha' : 'Mostrar senha';
  },
}));

/** Copia o link de convite para a área de transferência (com alternativa: selecionar o texto). */
Alpine.data('copyLink', () => ({
  copied: false,
  async copy() {
    const input = this.$refs.link;
    try {
      await navigator.clipboard.writeText(input.value);
      this.copied = true;
    } catch {
      input.select();
    }
  },
  get label() {
    return this.copied ? 'Link copiado' : 'Copiar link';
  },
}));

Alpine.data('personType', () => ({
  type: 'PJ',
  init() {
    this.type = this.$el.dataset.initial === 'PF' ? 'PF' : 'PJ';
  },
  get isPf() {
    return this.type === 'PF';
  },
  get isPj() {
    return this.type === 'PJ';
  },
  setPf() {
    this.type = 'PF';
  },
  setPj() {
    this.type = 'PJ';
  },
}));

/** Editor de tema com pré-visualização ao vivo e checagem de contraste (o servidor valida de novo). */
Alpine.data('themeEditor', () => ({
  primary: '',
  secondary: '',
  defaults: {},
  init() {
    const d = this.$el.dataset;
    this.primary = d.primary;
    this.secondary = d.secondary;
    this.defaults = { primary: d.defaultPrimary, secondary: d.defaultSecondary };
    this.$watch('primary', () => this.apply());
    this.$watch('secondary', () => this.apply());
  },
  apply() {
    const root = document.documentElement;
    for (const [name, value] of [
      ['primary', this.primary],
      ['secondary', this.secondary],
    ]) {
      if (!HEX.test(value)) continue;
      root.style.setProperty(`--fv-${name}`, value);
      root.style.setProperty(`--fv-on-${name}`, textOn(value));
    }
  },
  ratioText(value) {
    const r = contrastOf(value);
    if (r === null) return 'Use o formato #RRGGBB.';
    return `Contraste do texto: ${r.toFixed(2).replace('.', ',')}:1 ${r >= AA_NORMAL ? '— atende WCAG AA' : '— abaixo do mínimo 4,5:1'}`;
  },
  ok(value) {
    const r = contrastOf(value);
    return r !== null && r >= AA_NORMAL;
  },
  get primaryText() {
    return this.ratioText(this.primary);
  },
  get secondaryText() {
    return this.ratioText(this.secondary);
  },
  get primaryOk() {
    return this.ok(this.primary);
  },
  get secondaryOk() {
    return this.ok(this.secondary);
  },
  get valid() {
    return this.primaryOk && this.secondaryOk;
  },
  get invalid() {
    return !this.valid;
  },
  get primaryClass() {
    return this.primaryOk ? 'text-success' : 'text-danger';
  },
  get secondaryClass() {
    return this.secondaryOk ? 'text-success' : 'text-danger';
  },
  pickPrimary(e) {
    this.primary = e.target.value;
  },
  pickSecondary(e) {
    this.secondary = e.target.value;
  },
  restore() {
    this.primary = this.defaults.primary;
    this.secondary = this.defaults.secondary;
  },
}));

window.Alpine = Alpine;
Alpine.start();
