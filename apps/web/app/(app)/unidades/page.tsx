'use client';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { createUnitRequestSchema, type UnitDto } from '@fauvovet/contracts';
import { BuildingIcon } from '@/components/icons';
import { Alert, Button, Card, EmptyState, Field, PageHeader, Skeleton } from '@/components/ui';
import { ApiRequestError, api } from '@/lib/api';
import { useSession } from '@/lib/session';

export default function UnidadesPage() {
  const { can, refresh } = useSession();
  const [units, setUnits] = useState<UnitDto[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [form, setForm] = useState({ name: '', city: '', state: '' });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [feedback, setFeedback] = useState<{ tone: 'success' | 'danger'; text: string } | null>(
    null,
  );

  const load = useCallback(() => {
    setLoadError(null);
    api<UnitDto[]>('/units')
      .then(setUnits)
      .catch((e: Error) => setLoadError(e.message));
  }, []);
  useEffect(load, [load]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setFeedback(null);
    const parsed = createUnitRequestSchema.safeParse({
      name: form.name,
      city: form.city || undefined,
      state: form.state || undefined,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo',
    });
    if (!parsed.success) {
      const fe: Record<string, string> = {};
      for (const i of parsed.error.issues) fe[i.path.join('.')] ??= i.message;
      setErrors(fe);
      return;
    }
    setErrors({});
    setSaving(true);
    try {
      const created = await api<UnitDto>('/units', { method: 'POST', body: parsed.data });
      setUnits((u) =>
        [...(u ?? []), created].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')),
      );
      setForm({ name: '', city: '', state: '' });
      setFeedback({ tone: 'success', text: `Unidade “${created.name}” criada.` });
      void refresh();
    } catch (err) {
      if (err instanceof ApiRequestError) setErrors(err.fieldErrors());
      setFeedback({
        tone: 'danger',
        text: err instanceof Error ? err.message : 'Falha ao salvar.',
      });
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PageHeader
        title="Unidades"
        description="Estabelecimentos ou locais de atendimento da clínica. Cada unidade tem seu próprio fuso horário."
      />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <section aria-label="Lista de unidades">
          {loadError && (
            <Alert>
              {loadError}{' '}
              <button className="font-semibold underline" onClick={load}>
                Tentar novamente
              </button>
            </Alert>
          )}
          {!units && !loadError && (
            <div className="flex flex-col gap-3" aria-busy="true">
              <Skeleton className="h-20" />
              <Skeleton className="h-20" />
            </div>
          )}
          {units && units.length === 0 && (
            <EmptyState
              title="Nenhuma unidade cadastrada"
              icon={<BuildingIcon width={28} height={28} />}
            />
          )}
          {units && units.length > 0 && (
            <ul className="flex flex-col gap-3">
              {units.map((u) => (
                <li key={u.id}>
                  <Card className="flex items-start gap-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-link">
                      <BuildingIcon />
                    </span>
                    <div className="min-w-0">
                      <p className="font-semibold">{u.name}</p>
                      <p className="text-sm text-muted">
                        {[u.city, u.state].filter(Boolean).join(' · ') || 'Endereço não informado'}{' '}
                        · fuso {u.timezone}
                      </p>
                    </div>
                  </Card>
                </li>
              ))}
            </ul>
          )}
        </section>

        {can('units:create') && (
          <section aria-labelledby="nova-titulo" id="nova">
            <Card>
              <h2 id="nova-titulo" className="mb-4 text-lg font-semibold">
                Nova unidade
              </h2>
              <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
                <div aria-live="polite">
                  {feedback && <Alert tone={feedback.tone}>{feedback.text}</Alert>}
                </div>
                <Field
                  label="Nome"
                  required
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  error={errors.name}
                />
                <div className="grid grid-cols-[minmax(0,1fr)_5.5rem] gap-3">
                  <Field
                    label="Cidade"
                    value={form.city}
                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                    error={errors.city}
                  />
                  <Field
                    label="UF"
                    maxLength={2}
                    value={form.state}
                    onChange={(e) => setForm({ ...form, state: e.target.value.toUpperCase() })}
                    error={errors.state}
                  />
                </div>
                <Button type="submit" loading={saving}>
                  {saving ? 'Salvando…' : 'Criar unidade'}
                </Button>
              </form>
            </Card>
          </section>
        )}
      </div>
    </>
  );
}
