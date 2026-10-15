import { Inject, Injectable, OnModuleDestroy } from '@nestjs/common';
import { Pool, PoolClient } from 'pg';
import { CONFIG, Env } from '../config/env';

export interface DbContext {
  tenantId?: string | null;
  userId?: string | null;
}

/**
 * Acesso ao PostgreSQL com o papel da aplicação (sujeito a RLS).
 * O contexto de tenant/usuário é definido com set_config(..., true), válido apenas
 * dentro da transação — a conexão volta ao pool sem contexto residual.
 */
@Injectable()
export class DbService implements OnModuleDestroy {
  readonly pool: Pool;

  constructor(@Inject(CONFIG) config: Env) {
    this.pool = new Pool({
      connectionString: config.DATABASE_URL,
      max: config.DB_POOL_MAX,
      idleTimeoutMillis: 30_000,
      statement_timeout: 10_000,
      application_name: 'fauvovet-api',
    });
  }

  async withContext<T>(ctx: DbContext, fn: (client: PoolClient) => Promise<T>): Promise<T> {
    const client = await this.pool.connect();
    try {
      await client.query('BEGIN');
      await client.query(
        "SELECT set_config('app.tenant_id', $1, true), set_config('app.user_id', $2, true)",
        [ctx.tenantId ?? '', ctx.userId ?? ''],
      );
      const result = await fn(client);
      await client.query('COMMIT');
      return result;
    } catch (err) {
      await client.query('ROLLBACK').catch(() => undefined);
      throw err;
    } finally {
      client.release();
    }
  }

  async ping(): Promise<boolean> {
    const r = await this.pool.query('SELECT 1 AS ok');
    return r.rows[0]?.ok === 1;
  }

  async onModuleDestroy(): Promise<void> {
    await this.pool.end();
  }
}
