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

/**
 * Nascimento ↔ idade: ao escolher a data, preenche anos/meses; ao digitar a idade, limpa a data (o servidor
 * calcula uma data estimada). O servidor recalcula tudo — aqui é só conveniência.
 */
Alpine.data('birthAge', () => ({
  init() {
    this.fromBirth();
  },
  fromBirth() {
    const value = this.$refs.birth?.value;
    if (!value) return;
    const birth = new Date(`${value}T00:00:00`);
    const now = new Date();
    if (Number.isNaN(birth.getTime()) || birth > now) return;
    let months = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());
    if (now.getDate() < birth.getDate()) months -= 1;
    this.$refs.years.value = String(Math.floor(months / 12));
    this.$refs.months.value = String(months % 12);
  },
  fromAge() {
    if (this.$refs.birth.value) this.$refs.birth.value = '';
  },
}));

/**
 * CEP → endereço. Consulta o próprio FauvoVet (/cep/{cep}), que pergunta ao ViaCEP: a CSP do navegador continua
 * bloqueando conexões externas. Só preenche campos vazios ou ainda não editados; depois foca o "Número".
 */
Alpine.data('cepLookup', () => ({
  status: '',
  statusClass: 'text-muted',
  last: '',
  async lookup() {
    const digits = (this.$refs.cep.value || '').replace(/\D/g, '');
    if (digits.length === 8) this.$refs.cep.value = `${digits.slice(0, 5)}-${digits.slice(5)}`;
    if (digits.length !== 8 || digits === this.last) return;
    this.last = digits;
    this.status = 'Buscando endereço…';
    this.statusClass = 'text-muted';
    try {
      const res = await fetch(this.$root.dataset.url.replace('00000000', digits), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        this.status =
          res.status === 404
            ? 'CEP não encontrado. Preencha o endereço manualmente.'
            : 'Não foi possível consultar o CEP. Preencha manualmente.';
        this.statusClass = 'text-danger';
        return;
      }
      const set = (ref, value) => {
        if (ref && value) {
          ref.value = value;
          ref.dispatchEvent(new Event('change'));
        }
      };
      set(this.$refs.street, data.street);
      set(this.$refs.district, data.district);
      set(this.$refs.city, data.city);
      set(this.$refs.state, data.state);
      this.status = data.street ? 'Endereço encontrado. Informe o número.' : 'Cidade encontrada. Complete a rua.';
      this.statusClass = 'text-success';
      (data.street ? this.$refs.number : this.$refs.street)?.focus();
    } catch (e) {
      this.status =
        e.message && e.message.length < 90 ? e.message : 'Não foi possível consultar o CEP. Preencha manualmente.';
      this.statusClass = 'text-danger';
    }
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
