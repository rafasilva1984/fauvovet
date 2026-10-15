'use client';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { ROLE_LABELS, isTenantRole } from '@fauvovet/contracts';
import {
  BuildingIcon,
  ClockIcon,
  PaletteIcon,
  PlusIcon,
  ShieldIcon,
  UsersIcon,
} from '@/components/icons';
import { Alert, Card, EmptyState, PageHeader, Skeleton } from '@/components/ui';
import { api } from '@/lib/api';
import { useSession } from '@/lib/session';

interface AuditItem {
  id: string;
  action: string;
  actorName: string | null;
  createdAt: string;
}

const ACTION_LABELS: Record<string, string> = {
  'auth.context_selected': 'entrou no ambiente',
  'auth.logout': 'saiu',
  'unit.created': 'criou uma unidade',
  'tenant.theme_updated': 'alterou a aparência',
  'tenant.created': 'criou a clínica',
};

const DASHBOARD_COPY: Record<string, { title: string; text: string }> = {
  management: {
    title: 'Gestão da clínica',
    text: 'Acompanhe a estrutura da clínica, equipe e atividade recente.',
  },
  clinical: {
    title: 'Área clínica',
    text: 'Seu painel clínico mostrará agenda do dia, pacientes em atendimento e retornos.',
  },
  reception: {
    title: 'Recepção',
    text: 'Seu painel mostrará agenda do dia, chegadas e fila de espera.',
  },
  finance: { title: 'Financeiro', text: 'Seu painel mostrará recebimentos, caixa e pendências.' },
  operations: { title: 'Operação', text: 'Seu painel mostrará as tarefas do seu setor.' },
};

function greeting() {
  const h = new Date().getHours();
  return h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
}

function Stat({
  label,
  value,
  icon: Icon,
  href,
}: {
  label: string;
  value: number;
  icon: typeof UsersIcon;
  href?: string;
}) {
  const body = (
    <Card className="flex items-center gap-4 transition hover:border-brand/40">
      <span className="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link">
        <Icon />
      </span>
      <span>
        <span className="block text-2xl font-semibold tabular-nums">{value}</span>
        <span className="text-sm text-muted">{label}</span>
      </span>
    </Card>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

export default function PainelPage() {
  const { me, tenant, can } = useSession();
  const [audit, setAudit] = useState<AuditItem[] | null>(null);
  const [auditError, setAuditError] = useState<string | null>(null);
  const ctx = me!.context!;
  const copy = DASHBOARD_COPY[ctx.dashboard] ?? DASHBOARD_COPY.operations!;
  const canAudit = can('audit:read');

  useEffect(() => {
    if (!canAudit) return;
    api<AuditItem[]>('/audit')
      .then((a) => setAudit(a.slice(0, 8)))
      .catch((e: Error) => setAuditError(e.message));
  }, [canAudit]);

  const firstName = me!.user.name.split(' ')[0];
  return (
    <>
      <PageHeader
        title={`${greeting()}, ${firstName}`}
        description={`${copy.title} · ${copy.text}`}
      />

      <div className="mb-6 flex flex-wrap gap-1.5">
        {ctx.roles.map((r) => (
          <span
            key={r}
            className="rounded-full bg-surface-2 px-2.5 py-1 text-xs font-semibold text-muted"
          >
            {isTenantRole(r) ? ROLE_LABELS[r] : r}
          </span>
        ))}
      </div>

      {tenant && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Stat
            label={tenant.counts.units === 1 ? 'unidade' : 'unidades'}
            value={tenant.counts.units}
            icon={BuildingIcon}
            href="/unidades"
          />
          {can('members:read') && (
            <Stat
              label={tenant.counts.members === 1 ? 'membro ativo' : 'membros ativos'}
              value={tenant.counts.members}
              icon={UsersIcon}
              href="/equipe"
            />
          )}
          <Card className="flex items-center gap-4">
            <span className="flex size-11 items-center justify-center rounded-xl bg-surface-2 text-link">
              <ShieldIcon />
            </span>
            <span className="text-sm text-muted">
              Ambiente isolado: você vê somente dados de{' '}
              <strong className="text-ink">{tenant.tradeName}</strong>.
            </span>
          </Card>
        </div>
      )}

      <div className="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section aria-labelledby="modulos">
          <h2 id="modulos" className="mb-3 text-lg font-semibold">
            Atendimento
          </h2>
          <EmptyState
            title="Módulos clínicos em construção"
            icon={<ClockIcon width={28} height={28} />}
          >
            Cadastro de tutores e pacientes, agenda e prontuário chegam na Fase 1 do roadmap. Nada
            nesta tela é simulado: os números acima vêm do seu ambiente real.
          </EmptyState>
        </section>

        <section aria-labelledby="atalhos">
          <h2 id="atalhos" className="mb-3 text-lg font-semibold">
            Atalhos
          </h2>
          <div className="flex flex-col gap-2">
            {can('units:create') && (
              <Link
                href="/unidades#nova"
                className="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"
              >
                <PlusIcon /> Nova unidade
              </Link>
            )}
            {can('theme:update') && (
              <Link
                href="/configuracoes/aparencia"
                className="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"
              >
                <PaletteIcon /> Personalizar aparência
              </Link>
            )}
            <Link
              href="/unidades"
              className="flex min-h-12 items-center gap-3 rounded-xl border border-line bg-surface px-4 text-sm font-medium hover:bg-surface-2"
            >
              <BuildingIcon /> Ver unidades
            </Link>
          </div>
        </section>
      </div>

      {canAudit && (
        <section aria-labelledby="atividade" className="mt-8">
          <h2 id="atividade" className="mb-3 text-lg font-semibold">
            Atividade recente
          </h2>
          {auditError && <Alert>{auditError}</Alert>}
          {!audit && !auditError && <Skeleton className="h-40" />}
          {audit && audit.length === 0 && <EmptyState title="Sem atividade registrada" />}
          {audit && audit.length > 0 && (
            <Card className="p-0">
              <ul className="divide-y divide-line">
                {audit.map((a) => (
                  <li
                    key={a.id}
                    className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm"
                  >
                    <span>
                      <strong className="font-medium">{a.actorName ?? 'Sistema'}</strong>{' '}
                      <span className="text-muted">{ACTION_LABELS[a.action] ?? a.action}</span>
                    </span>
                    <time className="text-xs text-muted tabular-nums" dateTime={a.createdAt}>
                      {new Date(a.createdAt).toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                      })}
                    </time>
                  </li>
                ))}
              </ul>
            </Card>
          )}
        </section>
      )}
    </>
  );
}
