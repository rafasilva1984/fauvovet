'use client';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import type { MeResponse } from '@fauvovet/contracts';
import { AuthFrame } from '@/components/auth-frame';
import { EyeIcon } from '@/components/icons';
import { Alert, Button, Field } from '@/components/ui';
import { ApiRequestError, api, setCsrfToken } from '@/lib/api';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const me = await api<MeResponse>('/auth/login', {
        method: 'POST',
        body: { email, password },
      });
      setCsrfToken(me.csrfToken);
      router.replace(me.context ? '/painel' : '/contexto');
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Não foi possível entrar.');
      setLoading(false);
    }
  }

  return (
    <AuthFrame title="Entrar" subtitle="Acesse o ambiente da sua clínica.">
      <form onSubmit={onSubmit} className="flex flex-col gap-5" noValidate>
        {error && <Alert>{error}</Alert>}
        <Field
          label="E-mail"
          type="email"
          name="email"
          autoComplete="username"
          inputMode="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        <Field
          label="Senha"
          type={show ? 'text' : 'password'}
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          trailing={
            <button
              type="button"
              onClick={() => setShow((s) => !s)}
              className="flex size-10 items-center justify-center rounded-lg text-muted hover:text-ink"
              aria-label={show ? 'Ocultar senha' : 'Mostrar senha'}
              aria-pressed={show}
            >
              <EyeIcon />
            </button>
          }
        />
        <Button type="submit" loading={loading} className="w-full">
          {loading ? 'Entrando…' : 'Entrar'}
        </Button>
      </form>
      <p className="mt-8 text-sm text-muted">
        Ainda não usa o FauvoVet?{' '}
        <Link href="/cadastro" className="font-semibold text-link underline underline-offset-4">
          Cadastre sua clínica
        </Link>
      </p>
      <p className="mt-3 text-xs text-muted">
        Recuperação de senha por e-mail ainda não está disponível nesta versão.
      </p>
    </AuthFrame>
  );
}
