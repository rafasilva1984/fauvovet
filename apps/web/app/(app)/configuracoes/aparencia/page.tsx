'use client';
import { useEffect, useState } from 'react';
import {
  DEFAULT_THEME,
  HEX_COLOR,
  WCAG_AA_NORMAL,
  bestTextOn,
  contrastRatio,
  type ThemeTokens,
} from '@fauvovet/contracts';
import { LogoMark } from '@/components/icons';
import { Alert, Button, Card, PageHeader } from '@/components/ui';
import { api } from '@/lib/api';
import { useSession } from '@/lib/session';
import { applyTheme } from '@/lib/theme';

function contrastOf(color: string) {
  if (!HEX_COLOR.test(color)) return null;
  return contrastRatio(color, bestTextOn(color));
}

function ColorField({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
}) {
  const ratio = contrastOf(value);
  const ok = ratio !== null && ratio >= WCAG_AA_NORMAL;
  const id = `cor-${label.toLowerCase()}`;
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <div className="flex items-center gap-3">
        <input
          type="color"
          aria-label={`${label} — seletor`}
          value={HEX_COLOR.test(value) ? value : '#000000'}
          onChange={(e) => onChange(e.target.value)}
          className="size-11 cursor-pointer rounded-xl border border-line bg-surface p-1"
        />
        <input
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value.trim())}
          maxLength={7}
          spellCheck={false}
          aria-invalid={!ok || undefined}
          aria-describedby={`${id}-ratio`}
          className="min-h-11 w-32 rounded-xl border border-line bg-surface px-3 font-mono text-sm uppercase"
        />
      </div>
      <p id={`${id}-ratio`} className={`text-xs ${ok ? 'text-success' : 'text-danger'}`}>
        {ratio === null
          ? 'Use o formato #RRGGBB.'
          : `Contraste do texto: ${ratio.toFixed(2)}:1 ${ok ? '— atende WCAG AA' : `— abaixo do mínimo ${WCAG_AA_NORMAL}:1`}`}
      </p>
    </div>
  );
}

export default function AparenciaPage() {
  const { tenant, setTenant, can } = useSession();
  const [draft, setDraft] = useState<ThemeTokens>(tenant?.theme ?? DEFAULT_THEME);
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState<{ tone: 'success' | 'danger'; text: string } | null>(
    null,
  );
  const allowed = can('theme:update');

  // Pré-visualização ao vivo; ao sair sem salvar, restaura o tema salvo.
  useEffect(() => {
    if (HEX_COLOR.test(draft.primary) && HEX_COLOR.test(draft.secondary)) applyTheme(draft);
  }, [draft]);
  useEffect(() => () => applyTheme(tenant?.theme), [tenant]);

  if (!allowed) {
    return (
      <>
        <PageHeader title="Aparência" />
        <Alert tone="warning">Apenas o titular da clínica pode alterar a aparência.</Alert>
      </>
    );
  }

  const valid = [draft.primary, draft.secondary].every(
    (c) => (contrastOf(c) ?? 0) >= WCAG_AA_NORMAL,
  );

  async function save() {
    if (!tenant) return;
    setSaving(true);
    setFeedback(null);
    try {
      const saved = await api<ThemeTokens>('/tenant/theme', { method: 'PUT', body: draft });
      setTenant({ ...tenant, theme: saved });
      setFeedback({ tone: 'success', text: 'Aparência salva para toda a clínica.' });
    } catch (e) {
      setFeedback({ tone: 'danger', text: e instanceof Error ? e.message : 'Falha ao salvar.' });
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PageHeader
        title="Aparência"
        description="Cores e modo de exibição da sua clínica. Valem para todos os usuários do ambiente. Logo e timbre de documentos chegam em breve."
      />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="flex flex-col gap-5">
          <ColorField
            label="Primária"
            value={draft.primary}
            onChange={(primary) => setDraft({ ...draft, primary })}
          />
          <ColorField
            label="Secundária"
            value={draft.secondary}
            onChange={(secondary) => setDraft({ ...draft, secondary })}
          />
          <fieldset>
            <legend className="mb-2 text-sm font-medium">Modo</legend>
            <div className="grid grid-cols-3 gap-2">
              {(
                [
                  ['system', 'Automático'],
                  ['light', 'Claro'],
                  ['dark', 'Escuro'],
                ] as const
              ).map(([v, l]) => (
                <label
                  key={v}
                  className="flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-line text-sm font-medium has-[:checked]:border-brand has-[:checked]:bg-surface-2 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring"
                >
                  <input
                    type="radio"
                    name="mode"
                    value={v}
                    checked={draft.mode === v}
                    onChange={() => setDraft({ ...draft, mode: v })}
                    className="sr-only"
                  />
                  {l}
                </label>
              ))}
            </div>
          </fieldset>
          <div aria-live="polite">
            {feedback && <Alert tone={feedback.tone}>{feedback.text}</Alert>}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => void save()} loading={saving} disabled={!valid}>
              {saving ? 'Salvando…' : 'Salvar aparência'}
            </Button>
            <Button variant="secondary" onClick={() => setDraft(DEFAULT_THEME)}>
              Restaurar padrão FauvoVet
            </Button>
          </div>
          {!valid && (
            <p className="text-sm text-danger">
              Ajuste as cores para atender ao contraste mínimo antes de salvar.
            </p>
          )}
        </Card>

        <section aria-label="Pré-visualização">
          <Card className="flex flex-col gap-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted">
              Pré-visualização
            </p>
            <div className="flex items-center gap-3">
              <LogoMark size={40} />
              <span className="text-lg font-semibold">{tenant?.tradeName}</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <span className="inline-flex min-h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand">
                Botão primário
              </span>
              <span className="inline-flex min-h-10 items-center rounded-xl bg-accent px-4 text-sm font-semibold text-on-accent">
                Destaque
              </span>
            </div>
            <p className="text-sm">
              Texto com{' '}
              <span className="font-semibold text-link underline underline-offset-4">
                link de exemplo
              </span>{' '}
              sobre a superfície.
            </p>
          </Card>
        </section>
      </div>
    </>
  );
}
