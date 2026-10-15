'use client';
import { useEffect, useState } from 'react';
import { ROLE_LABELS, isTenantRole, type MemberDto } from '@fauvovet/contracts';
import { UsersIcon } from '@/components/icons';
import { Alert, Badge, Card, EmptyState, PageHeader, Skeleton } from '@/components/ui';
import { api } from '@/lib/api';
import { useSession } from '@/lib/session';

export default function EquipePage() {
  const { can } = useSession();
  const [members, setMembers] = useState<MemberDto[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const allowed = can('members:read');

  useEffect(() => {
    if (!allowed) return;
    api<MemberDto[]>('/members')
      .then(setMembers)
      .catch((e: Error) => setError(e.message));
  }, [allowed]);

  if (!allowed) {
    return (
      <>
        <PageHeader title="Equipe" />
        <Alert tone="warning">Seu papel não permite visualizar a equipe desta clínica.</Alert>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Equipe"
        description="Pessoas com acesso a esta clínica e seus papéis. Convites e edição de papéis chegam na Fase 1."
      />
      {error && <Alert>{error}</Alert>}
      {!members && !error && <Skeleton className="h-48" />}
      {members && members.length === 0 && (
        <EmptyState title="Nenhum membro" icon={<UsersIcon width={28} height={28} />} />
      )}
      {members && members.length > 0 && (
        <Card className="p-0">
          <ul className="divide-y divide-line">
            {members.map((m) => (
              <li
                key={m.membershipId}
                className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="truncate font-medium">{m.name}</p>
                  <p className="truncate text-sm text-muted">{m.email}</p>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {m.roles.map((r) => (
                    <Badge key={r}>{isTenantRole(r) ? ROLE_LABELS[r] : r}</Badge>
                  ))}
                  {m.status !== 'active' && <Badge>{m.status}</Badge>}
                </div>
              </li>
            ))}
          </ul>
        </Card>
      )}
    </>
  );
}
