# Segurança — FauvoVet

> Não há aprovação jurídica/LGPD. Este documento lista controles **implementados** e **pendentes**.

## Implementado (Fase 0)

| Controle                                                                                                 | Onde                                     | Teste                                         |
| -------------------------------------------------------------------------------------------------------- | ---------------------------------------- | --------------------------------------------- |
| RLS por tenant (`USING` + `WITH CHECK`), papel da app sem BYPASSRLS/posse                                | `migrations/0001_foundation.sql`         | `tenant-isolation.test.ts`                    |
| Contexto RLS local à transação (sem vazamento no pool)                                                   | `db/db.service.ts`                       | `contexto é local à transação`                |
| FKs compostas (tenant_id, id)                                                                            | `membership_units`, `user_sessions`      | `FK composta impede...`                       |
| Tenant derivado da sessão; IDs/`tenant_id` manipulados ignorados ou 404                                  | `auth.service.ts`, `tenancy.service.ts`  | `tenant_id manipulado...`, `ID previsível...` |
| Argon2id, bloqueio por falhas, mensagem uniforme, tempo equalizado                                       | `auth/`                                  | `auth.test.ts`                                |
| Cookie `HttpOnly`/`SameSite=Lax`/`Secure` (prod obrigatório)/`Path=BASE_PATH`; só hash do token no banco | `auth.controller.ts`, `config/env.ts`    | `define cookie HttpOnly...`, `env.test.ts`    |
| CSRF por sessão + verificação de `Origin`                                                                | `session.guard.ts`                       | `CSRF e origem`                               |
| RBAC negar-por-padrão no backend                                                                         | `contracts/roles.ts`, `session.guard.ts` | `rbac.test.ts`                                |
| Rate limit (login, onboarding) — memória, instância única                                                | `common/rate-limit.ts`                   | `rate-limit.test.ts`                          |
| Helmet (CSP `default-src 'none'` na API), sem `x-powered-by`, corpo ≤ 100 kB                             | `app.factory.ts`                         | manual                                        |
| Auditoria append-only (trigger + sem GRANT de UPDATE/DELETE)                                             | migração                                 | `auditoria é append-only`                     |
| Logs sem corpo/query/cookies, tenant pseudonimizado                                                      | `common/request-context.ts`              | revisão                                       |
| Guardas: testes só em banco local `*_test`; seed/migrate recusam host não local fora de produção         | `config/env.ts`, CLIs                    | `env.test.ts`                                 |
| Varredura de segredos local + `.gitignore` restritivo                                                    | `scripts/secrets-scan.mjs`               | `pnpm security:secrets`                       |

## Pendente (com fase)

- MFA para `tenant_owner`/`platform_admin`; recuperação de senha; gestão de sessões/dispositivos (Fase 1).
- Rate limit distribuído (Redis) antes de múltiplas réplicas.
- Auditoria de **visualização** de prontuário (quando existir prontuário — Fase 1).
- Criptografia de anexos em repouso, URLs assinadas curtas, antivírus em upload (Fase 1–2).
- Backups criptografados com restauração testada; RPO/RTO (antes da operação comercial).
- Revisão LGPD/CFMV por profissional (CLAUDE.md §8.2) — **obrigatória antes de comercializar**.
- `pnpm audit` e gitleaks na CI.

## Reporte de vulnerabilidades

Abra uma _security advisory_ privada no GitHub do repositório (não use issues públicas para falhas de segurança).
