import { Errors } from './errors';

/**
 * Limitador em memória por janela deslizante simples.
 * Limitação: válido para uma única instância da API. Para múltiplas réplicas, migrar para Redis
 * (registrado em docs/IDEAS.md).
 */
export class RateLimiter {
  private readonly hits = new Map<string, number[]>();

  constructor(
    private readonly limit: number,
    private readonly windowMs: number,
  ) {}

  consume(key: string): void {
    const now = Date.now();
    const recent = (this.hits.get(key) ?? []).filter((t) => now - t < this.windowMs);
    if (recent.length >= this.limit) {
      this.hits.set(key, recent);
      throw Errors.rateLimited();
    }
    recent.push(now);
    this.hits.set(key, recent);
    if (this.hits.size > 50_000) this.sweep(now);
  }

  private sweep(now: number): void {
    for (const [k, v] of this.hits)
      if (v.every((t) => now - t >= this.windowMs)) this.hits.delete(k);
  }

  reset(): void {
    this.hits.clear();
  }
}
