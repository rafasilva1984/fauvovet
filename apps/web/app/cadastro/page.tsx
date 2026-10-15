'use client';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import { onboardingRequestSchema, type MeResponse } from '@fauvovet/contracts';
import { AuthFrame } from '@/components/auth-frame';
import { Alert, Button, Field } from '@/components/ui';
import { ApiRequestError, api, setCsrfToken } from '@/lib/api';

type Form = {
  personType: 'PF' | 'PJ';
  tradeName: string;
  legalName: string;
  document: string;
  ownerName: string;
  ownerCrmv: string;
  email: string;
  password: string;
  unitName: string;
};

const initial: Form = {
  personType: 'PJ',
  tradeName: '',
  legalName: '',
  document: '',
  ownerName: '',
  ownerCrmv: '',
  email: '',
  password: '',
  unitName: '',
};

export default function CadastroPage() {
  const router = useRouter();
  const [form, setForm] = useState<Form>(initial);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const set = (k: keyof Form) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const payload = {
      ...form,
      legalName: form.legalName || undefined,
      document: form.document || undefined,
      ownerCrmv: form.ownerCrmv || undefined,
      unitName: form.unitName || (form.personType === 'PF' ? 'Atendimento principal' : 'Matriz'),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo',
    };
    const parsed = onboardingRequestSchema.safeParse(payload);
    if (!parsed.success) {
      const fe: Record<string, string> = {};
      for (const i of parsed.error.issues) fe[i.path.join('.')] ??= i.message;
      setErrors(fe);
      return;
    }
    setErrors({});
    setLoading(true);
    try {
      await api('/onboarding', { method: 'POST', body: parsed.data });
      const me = await api<MeResponse>('/auth/login', {
        method: 'POST',
        body: { email: parsed.data.email, password: parsed.data.password },
      });
      setCsrfToken(me.csrfToken);
      router.replace(me.context ? '/painel' : '/contexto');
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setErrors(err.fieldErrors());
        setError(err.message);
      } else setError('Não foi possível concluir o cadastro.');
      setLoading(false);
    }
  }

  const pf = form.personType === 'PF';
  return (
    <AuthFrame
      wide
      title="Cadastre sua clínica"
      subtitle="Crie o ambiente isolado da sua clínica ou do seu atendimento autônomo."
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-6" noValidate>
        {error && <Alert>{error}</Alert>}
        <fieldset className="flex flex-col gap-3">
          <legend className="mb-2 text-sm font-medium">Tipo de cadastro</legend>
          <div className="grid grid-cols-2 gap-2 rounded-2xl bg-surface-2 p-1" role="radiogroup">
            {(['PJ', 'PF'] as const).map((t) => (
              <label
                key={t}
                className={`flex min-h-11 cursor-pointer items-center justify-center rounded-xl px-3 text-center text-sm font-semibold transition has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring ${
                  form.personType === t ? 'bg-surface text-ink shadow-card' : 'text-muted'
                }`}
              >
                <input
                  type="radio"
                  name="personType"
                  value={t}
                  checked={form.personType === t}
                  onChange={() => setForm((f) => ({ ...f, personType: t, document: '' }))}
                  className="sr-only"
                />
                {t === 'PJ' ? 'Clínica / hospital (CNPJ)' : 'Profissional autônomo (CPF)'}
              </label>
            ))}
          </div>
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label={pf ? 'Nome profissional' : 'Nome fantasia'}
            required
            value={form.tradeName}
            onChange={set('tradeName')}
            error={errors.tradeName}
            className="sm:col-span-2"
          />
          {!pf && (
            <Field
              label="Razão social"
              value={form.legalName}
              onChange={set('legalName')}
              error={errors.legalName}
            />
          )}
          <Field
            label={pf ? 'CPF (opcional)' : 'CNPJ (opcional)'}
            inputMode="numeric"
            value={form.document}
            onChange={set('document')}
            error={errors.document}
            hint="Validamos apenas os dígitos verificadores."
            className={pf ? 'sm:col-span-2' : ''}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Seu nome"
            required
            autoComplete="name"
            value={form.ownerName}
            onChange={set('ownerName')}
            error={errors.ownerName}
          />
          <Field
            label="CRMV (opcional)"
            value={form.ownerCrmv}
            onChange={set('ownerCrmv')}
            error={errors.ownerCrmv}
            hint="Ex.: CRMV-SP 00000"
          />
          <Field
            label="E-mail de acesso"
            type="email"
            required
            autoComplete="email"
            inputMode="email"
            value={form.email}
            onChange={set('email')}
            error={errors.email}
          />
          <Field
            label="Senha"
            type="password"
            required
            autoComplete="new-password"
            value={form.password}
            onChange={set('password')}
            error={errors.password}
            hint="Mínimo de 12 caracteres."
          />
          <Field
            label="Nome da primeira unidade"
            value={form.unitName}
            onChange={set('unitName')}
            error={errors.unitName}
            placeholder={pf ? 'Atendimento principal' : 'Matriz'}
            className="sm:col-span-2"
          />
        </div>

        <Button type="submit" loading={loading} className="w-full sm:w-auto sm:self-start">
          {loading ? 'Criando ambiente…' : 'Criar ambiente'}
        </Button>
      </form>
      <p className="mt-8 text-sm text-muted">
        Já tem conta?{' '}
        <Link href="/login" className="font-semibold text-link underline underline-offset-4">
          Entrar
        </Link>
      </p>
    </AuthFrame>
  );
}
