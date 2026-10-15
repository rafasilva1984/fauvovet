# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-15 14:10 (America/Sao_Paulo) · **Fase:** 0 — Fundação (em andamento)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Objetivo da sessão

Iniciar a Fase 0: monorepo, banco com RLS, API multi-tenant (login, tenant, RBAC), testes de isolamento, docs e CI;
em seguida a web (login, onboarding, contexto, shell responsivo, tema).

## Situação atual

- **Branch/remote:** `main` → `origin` (https://github.com/rafasilva1984/fauvovet). Último commit remoto antes desta
  sessão: `0b24a5c`. Sincronização deste incremento: ver tabela de histórico.
- **Implementado e testado (API, sem UI):** auth (login/logout/me), sessão HttpOnly + CSRF + Origin, bloqueio por
  falhas, seleção de tenant/unidade, RBAC (8 papéis), onboarding PF/PJ, unidades (listar/obter/criar), tema por tenant
  com contraste AA, membros, auditoria append-only, health, RLS em todas as tabelas.
- **Não implementado:** interface web, E2E, Docker Compose, convites/gestão de usuários, MFA, recuperação de senha,
  qualquer módulo clínico. Nada é _mock_; nada está publicado.
- **Último guia HTML:** [`docs/changes/2026-10-15-fundacao-api-multitenant.html`](changes/2026-10-15-fundacao-api-multitenant.html)

## Ambiente (verificado)

WSL2 Ubuntu 26.04, Node 24.21.0, pnpm 12.4.2, Git 2.53.0, mise 2026.9.11, gh 2.101 (autenticado). Sem sudo.
Docker Desktop instalado no Windows, **daemon parado e sem integração WSL** → PostgreSQL 18.4 local via
`embedded-postgres` (ADR-005). Git Bash: não verificado.

## Arquivos/migrações deste incremento

- Migração `apps/api/migrations/0001_foundation.sql` (8 tabelas, RLS, 6 funções).
- `packages/contracts/`, `apps/api/`, `scripts/`, `.github/workflows/ci.yml`, docs (ver guia HTML §2).

## Comandos executados e resultados

- `pnpm db:setup` → papéis e bancos `_dev`/`_test` criados. `pnpm db:migrate` → 0001 aplicada. `pnpm db:seed` → 2 tenants, 3 unidades, 6 usuários.
- `pnpm verify` → PASS em todas as etapas (E2E: NOT RUN, sem web).
- `pnpm test:integration` → 42/42; `pnpm test:tenant-isolation` → 15/15.
- `pnpm perf:check` → p95 health 2,34 ms · me 6,25 ms · units 7,37 ms · tenant 5,61 ms (baseline, sem limites).

## Riscos / bloqueios

- Plano Locaweb não verificado → deploy bloqueado (decisão do proprietário + dados do plano).
- Docker Compose pendente de ambiente com Docker para teste.
- Licença MIT aplicada por recomendação do CLAUDE.md (ADR-006) — reversível pelo proprietário.
- `embedded-postgres` é empacotamento beta (PostgreSQL 18.4 estável) — só dev/CI.

## Próxima ação exata

1. `git pull --ff-only && pnpm db:start`.
2. Criar `apps/web` (Next.js 16, React 19, Tailwind 4) com `basePath` de `BASE_PATH` e _rewrite_ de
   `/api/:path*` → `API_INTERNAL_URL/${BASE_PATH}/api/:path*`.
3. Telas: `/login`, `/cadastro` (onboarding), `/contexto`, shell autenticado (sidebar desktop / barra inferior mobile),
   `/painel` por função, `/unidades`, `/configuracoes/aparencia` (tema por tenant via tokens CSS).
4. E2E Playwright (`pnpm test:e2e`) desktop + viewport mobile; integrar ao `pnpm verify --e2e`.
5. Novo guia `docs/changes/2026-10-15-web-shell-login.html`, atualizar este checkpoint, verify, commit, push.

## Histórico de incrementos

| Data       | Incremento                                    | HTML                                                                                         | Testes                               | Commit/branch                      | Sync GitHub       | Próximo passo             |
| ---------- | --------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------- | ----------------- | ------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação) | Não aplicável                                                                                | Não executados                       | `0b24a5c` (main)                   | Já no remoto      | Iniciar Fase 0            |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant     | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42) | main — hash na próxima atualização | push nesta sessão | Web: login/contexto/shell |
