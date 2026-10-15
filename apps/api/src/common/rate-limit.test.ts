import { describe, expect, it } from 'vitest';
import { RateLimiter } from './rate-limit';

describe('RateLimiter', () => {
  it('bloqueia após o limite dentro da janela', () => {
    const rl = new RateLimiter(3, 60_000);
    rl.consume('k');
    rl.consume('k');
    rl.consume('k');
    expect(() => rl.consume('k')).toThrow();
    expect(() => rl.consume('outra')).not.toThrow();
  });
});
