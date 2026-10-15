# Arquitetura — FauvoVet

> Estado: **Fase 0 em andamento**. Este documento descreve o que **existe no código** e marca o que é planejado.

## Visão geral

```text
navegador ──HTTPS──▶ proxy reverso (nginx, planejado) ──▶ /fauvovet          → apps/web (Next.js)  [incremento 2]
                                                     └──▶ /fauvovet/api/v1/* → apps/api (NestJS)  [implementado]
                                                                                   │
                                                                                   ▼
                                                                 PostgreSQL 18 (RLS por tenant)
```

| Peça                                                                 | Local                 | Estado                            |
| -------------------------------------------------------------------- | --------------------- | --------------------------------- |
| Contratos tipados (zod, papéis, permissões, validações)              | `packages/contracts`  | Implementado                      |
| API (auth, contexto, RBAC, tenancy, onboarding, auditoria, health)   | `apps/api`            | Implementado                      |
| Migrações SQL versionadas com checksum                               | `apps/api/migrations` | Implementado (0001)               |
| Web (login, onboarding, seleção de contexto, shell responsivo, tema) | `apps/web`            | Próximo incremento                |
| Worker (filas, extração, indexação)                                  | `apps/worker`         | Não iniciado (Fase 2)             |
| Redis/BullMQ                                                         | —                     | Não iniciado (quando houver jobs) |
| Armazenamento de objetos privado                                     | —                     | Não iniciado (Fase 1, anexos)     |

## Princípios aplicados no código

1. **Tenant derivado da sessão, nunca do cliente.** `POST /auth/context` aceita um `membershipId`, que é validado contra
   `app.my_memberships()` (vínculos do usuário autenticado). `tenant_id` enviado em corpo/query/cabeçalho é ignorado
   (teste `tenant_id manipulado...`).
2. **RLS como barreira final.** O papel `fauvovet_app` não é dono das tabelas, não é superuser e não tem `BYPASSRLS`.
   Toda consulta de domínio roda em `DbService.withContext()`, que abre transação e define
   `set_config('app.tenant_id', ..., true)` — **local à transação**, sem vazamento entre requisições do pool.
3. **Funções `SECURITY DEFINER` mínimas** para o que precede a escolha de contexto (login, resolução de sessão,
   listagem dos próprios vínculos). Todas com `search_path` fixo e `EXECUTE` revogado de `PUBLIC`.
4. **Negar por padrão.** `SessionGuard` global: toda rota exige sessão, exceto `@Public()`. Rotas com
   `@RequirePermission()` exigem contexto ativo e permissão do papel (matriz em `packages/contracts/src/roles.ts`).
5. **Base path configurável.** API em `${BASE_PATH}/api/v1` (padrão `/fauvovet/api/v1`); cookie com `Path=${BASE_PATH}`.
   Hospedar na raiz ou em subdomínio = `BASE_PATH=` vazio, sem alterar código.
6. **Erros padronizados** `{ error: { code, message, details?, requestId } }`, sem stack. Logs de acesso JSON com
   `requestId` e tenant **pseudonimizado** (hash), sem query string, corpo ou cookies.

## Fluxo de requisição (API)

```text
requestContextMiddleware (requestId, log) → helmet → json(100kb) → cookieParser
  → SessionGuard: Origin (mutações) → @Public? → cookie fv_session → app.auth_resolve_session(sha256(token))
                  → CSRF (X-CSRF-Token == token da sessão, mutações) → @RequirePermission (contexto + RBAC)
  → Controller → parseBody(zod) → Service → DbService.withContext({tenantId, userId}) → SQL parametrizado (RLS)
  → AllExceptionsFilter
```

## Pastas

```text
apps/api/src/
  auth/        login, sessão, CSRF, guard global, seleção de contexto
  tenancy/     tenant, tema, unidades, membros, auditoria (leitura)
  onboarding/  cadastro de clínica/profissional (ALLOW_SELF_SIGNUP)
  audit/       gravação append-only
  db/          pool com contexto RLS, migrador, seed fictício
  common/      erros, decorators, rate limit, request id
  perf/        benchmark reproduzível (pnpm perf:check)
apps/api/test/ integração (supertest + PostgreSQL real *_test) e isolation/
packages/contracts/src/  schemas e tipos compartilhados web ⇄ API
scripts/       CLI portátil em Node (bootstrap, dev, db-local, verify, docs:validate, secrets, smoke)
```

Decisões e alternativas: `docs/DECISIONS.md`. Modelo de dados: `docs/DATA_MODEL.md`.
