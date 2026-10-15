# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-15 15:40 (America/Sao_Paulo) · **Fase:** 0 — Fundação (em andamento)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Objetivo da sessão

Iniciar a Fase 0: monorepo, banco com RLS, API multi-tenant (login, tenant, RBAC), testes de isolamento, docs e CI;
em seguida a web (login, onboarding, contexto, shell responsivo, tema).

## Situação atual

- **Branch/remote:** `main` → `origin` (https://github.com/rafasilva1984/fauvovet). Último commit de código sincronizado:
  `4a9ade7` (web). Working tree limpa após este registro.
- **Implementado e testado:**
  - API: auth (login/logout/me), sessão HttpOnly + CSRF + Origin, bloqueio por falhas, seleção de tenant/unidade,
    RBAC (8 papéis), onboarding PF/PJ, unidades (listar/obter/criar), tema por tenant (contraste AA), membros,
    auditoria append-only, health, RLS em todas as tabelas.
  - Web: `/login`, `/cadastro`, `/contexto`, shell responsivo (sidebar desktop; topo + barra inferior no celular),
    `/painel` por função, `/unidades`, `/equipe` (leitura), `/configuracoes/aparencia`; tema claro/escuro.
- **Não implementado:** Docker Compose, convites/edição de usuários, MFA, recuperação de senha, logo do tenant e
  qualquer módulo clínico. Nada é _mock_; nada está publicado.
- **Último guia HTML:** [`docs/changes/2026-10-15-web-shell-login.html`](changes/2026-10-15-web-shell-login.html)

## Ambiente (verificado)

WSL2 Ubuntu 26.04, Node 24.21.0, pnpm 12.4.2, Git 2.53.0, mise 2026.9.11, gh 2.101 (autenticado), Chromium headless
156 (Playwright 1.64.1). Sem sudo. Docker Desktop no Windows **sem integração WSL** → PostgreSQL 18.4 local via
`embedded-postgres` (ADR-005). Git Bash: não verificado.

## Comandos executados e resultados (esta sessão)

- `pnpm db:setup`, `db:migrate` (0001), `db:seed` (2 tenants, 3 unidades, 6 usuários).
- `pnpm verify` → PASS (também em clone limpo após `pnpm bootstrap`).
- `pnpm test:integration` → 42/42 (isolamento 15/15). Unitários: contracts 12, api 5, web 4.
- `pnpm test:e2e` → 22/22 (desktop + Pixel 7; axe AA claro/escuro; ~33 s no total após contornar a rede _mirrored_ do WSL).
- `pnpm perf:check` → p95 health 2,34 ms · me 6,25 ms · units 7,37 ms · tenant 5,61 ms.
- Web Vitals (laboratório, sem throttling): LCP login 40–88 ms, painel 96–176 ms, CLS 0, JS ~244–249 KB.
- `pnpm smoke` via `pnpm dev` → PASS (health, login page, 401, login/me/logout pelo proxy da web).
- CI do commit `7d303af` (run 38774668829): **success** — bootstrap + verify em ubuntu-latest (38 s).

## Riscos / bloqueios

- Plano Locaweb não verificado → deploy bloqueado (precisa dos dados do plano fornecidos pelo proprietário).
- Docker Compose pendente de ambiente com Docker para teste.
- Licença MIT aplicada por recomendação do CLAUDE.md (ADR-006) — reversível pelo proprietário.
- `embedded-postgres` é empacotamento beta (PostgreSQL 18.4 estável) — só dev/CI.
- E2E roda na CI desde `4a9ade7` (run 38786064647: **success**).
- CSP da web com `'unsafe-inline'` em scripts (ADR-009) — revisar antes de produção.

## Próxima ação exata

1. `git pull --ff-only && pnpm db:start`.
2. Conferir a CI do último push (`gh run list --limit 3`).
3. Fase 1, primeira fatia: gestão de equipe (convite por link com expiração, edição de papéis, desativação) com
   auditoria e testes de isolamento; depois migração `0002` de responsáveis/pacientes (RLS + FKs compostas).
4. Novo guia HTML em `docs/changes/`, atualizar este checkpoint, `pnpm verify --e2e`, commit, push.

## Histórico de incrementos

| Data       | Incremento                                                                            | HTML                                                                                         | Testes                               | Commit/branch                      | Sync GitHub            | Próximo passo                         |
| ---------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------ | ---------------------------------- | ---------------------- | ------------------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação)                                         | Não aplicável                                                                                | Não executados                       | `0b24a5c` (main)                   | Já no remoto           | Iniciar Fase 0                        |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant                                             | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42) | `7d303af` (main)                   | PASS (push confirmado) | Web: login/contexto/shell             |
| 2026-10-15 | Web: login, cadastro, contexto, shell responsivo, painel, unidades, equipe, aparência | [2026-10-15-web-shell-login.html](changes/2026-10-15-web-shell-login.html)                   | unit 21, integração 42, E2E 22       | main — hash na próxima atualização | push nesta sessão      | E2E na CI; Fase 1 (equipe, pacientes) |
