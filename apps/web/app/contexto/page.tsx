'use client';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { ROLE_LABELS, isTenantRole, type MeResponse } from '@fauvovet/contracts';
import { AuthFrame } from '@/components/auth-frame';
import { Alert, Badge, Button, Skeleton } from '@/components/ui';
import { ApiRequestError, api } from '@/lib/api';
import { SessionProvider, useSession } from '@/lib/session';

function ContextChooser() {
  const router = useRouter();
  const { me, status, error, setMe, logout } = useSession();
  const [unitBy, setUnitBy] = useState<Record<string, string>>({});
  const [pending, setPending] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  useEffect(() => {
    if (status === 'unauthenticated') router.replace('/login');
  }, [status, router]);

  if (status === 'loading' || status === 'unauthenticated') {
    return (
      <div className="flex flex-col gap-3" aria-busy="true">
        <Skeleton className="h-28" />
        <Skeleton className="h-28" />
      </div>
    );
  }
  if (status === 'error' || !me) return <Alert>{error ?? 'Falha ao carregar.'}</Alert>;

  async function choose(membershipId: string, defaultUnitId: string | undefined) {
    setPending(membershipId);
    setFailure(null);
    try {
      const unitId = unitBy[membershipId] ?? defaultUnitId;
      const fresh = await api<MeResponse>('/auth/context', {
        method: 'POST',
        body: { membershipId, ...(unitId ? { unitId } : {}) },
      });
      setMe(fresh);
      router.replace('/painel');
    } catch (err) {
      setFailure(err instanceof ApiRequestError ? err.message : 'Não foi possível selecionar.');
      setPending(null);
    }
  }

  if (me.memberships.length === 0) {
    return (
      <div className="flex flex-col gap-4">
        <Alert tone="warning">
          Seu usuário não possui vínculo ativo com nenhuma clínica. Peça um convite ao responsável.
        </Alert>
        <Button
          variant="secondary"
          onClick={() => void logout().then(() => router.replace('/login'))}
        >
          Sair
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {failure && <Alert>{failure}</Alert>}
      <ul className="flex flex-col gap-3">
        {me.memberships.map((m) => {
          const active = me.context?.membershipId === m.membershipId;
          return (
            <li
              key={m.membershipId}
              className="rounded-2xl border border-line bg-surface p-5 shadow-card"
            >
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h2 className="font-semibold text-ink">{m.tenantName}</h2>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {m.roles.map((r) => (
                      <Badge key={r}>{isTenantRole(r) ? ROLE_LABELS[r] : r}</Badge>
                    ))}
                  </div>
                </div>
                {active && <Badge tone="brand">Atual</Badge>}
              </div>
              {m.units.length > 1 && (
                <fieldset className="mt-4">
                  <legend className="mb-2 text-sm font-medium">Unidade</legend>
                  <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    {m.units.map((u) => (
                      <label
                        key={u.id}
                        className="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line px-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-surface-2"
                      >
                        <input
                          type="radio"
                          name={`unit-${m.membershipId}`}
                          value={u.id}
                          checked={(unitBy[m.membershipId] ?? m.units[0]?.id) === u.id}
                          onChange={() => setUnitBy((s) => ({ ...s, [m.membershipId]: u.id }))}
                          className="accent-[var(--fv-primary)]"
                        />
                        {u.name}
                      </label>
                    ))}
                  </div>
                </fieldset>
              )}
              <Button
                className="mt-4 w-full sm:w-auto"
                loading={pending === m.membershipId}
                disabled={pending !== null && pending !== m.membershipId}
                onClick={() => void choose(m.membershipId, m.units[0]?.id)}
              >
                Entrar em {m.tenantName}
              </Button>
            </li>
          );
        })}
      </ul>
      <Button
        variant="ghost"
        className="self-start"
        onClick={() => void logout().then(() => router.replace('/login'))}
      >
        Sair
      </Button>
    </div>
  );
}

export default function ContextoPage() {
  return (
    <SessionProvider>
      <AuthFrame
        title="Escolha onde trabalhar"
        subtitle="Cada clínica é um ambiente separado. Você verá apenas os dados da clínica selecionada."
      >
        <ContextChooser />
      </AuthFrame>
    </SessionProvider>
  );
}
