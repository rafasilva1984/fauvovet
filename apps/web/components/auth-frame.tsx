import type { ReactNode } from 'react';
import { LogoMark, ShieldIcon } from './icons';

/** Moldura das telas públicas (login, cadastro, seleção de contexto). */
export function AuthFrame({
  title,
  subtitle,
  children,
  wide,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
  wide?: boolean;
}) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <aside className="relative hidden overflow-hidden bg-brand text-on-brand lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div className="flex items-center gap-3">
          <LogoMark size={40} />
          <span className="text-xl font-semibold tracking-tight">FauvoVet</span>
        </div>
        <div className="relative z-10 max-w-md">
          <p className="text-3xl font-semibold leading-tight tracking-tight">
            O histórico clínico de cada paciente, organizado e protegido.
          </p>
          <p className="mt-4 opacity-85">
            Plataforma para veterinários autônomos, clínicas e hospitais — cada clínica com seu
            ambiente isolado.
          </p>
        </div>
        <p className="relative z-10 flex items-center gap-2 text-sm opacity-85">
          <ShieldIcon /> Dados isolados por clínica · sessão protegida
        </p>
        <div
          aria-hidden="true"
          className="absolute -bottom-24 -right-24 size-[28rem] rounded-full opacity-20"
          style={{ background: 'radial-gradient(circle, var(--fv-secondary), transparent 65%)' }}
        />
      </aside>
      <main
        id="conteudo"
        className="flex items-start justify-center px-4 py-10 sm:items-center sm:px-8"
      >
        <div className={wide ? 'w-full max-w-2xl' : 'w-full max-w-md'}>
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <LogoMark size={36} />
            <span className="text-lg font-semibold tracking-tight">FauvoVet</span>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h1>
          {subtitle && <p className="mt-2 text-muted">{subtitle}</p>}
          <div className="mt-8">{children}</div>
        </div>
      </main>
    </div>
  );
}
