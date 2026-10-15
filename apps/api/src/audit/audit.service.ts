import { Injectable } from '@nestjs/common';
import type { PoolClient } from 'pg';

export interface AuditEntry {
  tenantId: string | null;
  actorUserId: string | null;
  action: string;
  targetType?: string;
  targetId?: string;
  metadata?: Record<string, unknown>;
  requestId?: string;
}

/** Trilha de auditoria append-only. Nunca registrar senhas, tokens ou conteúdo clínico bruto. */
@Injectable()
export class AuditService {
  async record(client: PoolClient, e: AuditEntry): Promise<void> {
    await client.query(
      `INSERT INTO audit_events (tenant_id, actor_user_id, action, target_type, target_id, metadata, request_id)
       VALUES ($1, $2, $3, $4, $5, $6, $7)`,
      [
        e.tenantId,
        e.actorUserId,
        e.action,
        e.targetType ?? null,
        e.targetId ?? null,
        e.metadata ?? {},
        e.requestId ?? null,
      ],
    );
  }
}
