# FauvoVet — Estratégia e evidências de testes

## Ambientes

| Ambiente                                | Estado         | Observações                                                                                                                                                                                  |
| --------------------------------------- | -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| WSL2 Ubuntu 26.04 (kernel 6.18, x86_64) | **Verificado** | Node 24.21.0, pnpm 12.4.2, Git 2.53.0, mise 2026.9.11; sem sudo; Docker Desktop instalado no Windows mas sem integração WSL; Chromium headless 156 (Playwright 1.64) sem dependências extras |
| CI GitHub Actions `ubuntu-latest`       | Configurado    | `pnpm bootstrap` + `pnpm verify` (E2E ainda fora da CI)                                                                                                                                      |
| Git Bash (Windows)                      | **NOT RUN**    | Scripts em Node puro e binário PostgreSQL Windows previstos, mas não executados                                                                                                              |
| macOS                                   | NOT RUN        | —                                                                                                                                                                                            |

Bancos: `fauvovet_dev` (desenvolvimento, seed fictício) e `fauvovet_test` (schema recriado a cada `test:integration`
e `test:e2e`), no PostgreSQL 18.4 local em `127.0.0.1:54329`. Papéis: `fauvovet_migrator` (dono) e `fauvovet_app`
(RLS aplicada).

## Comandos reais disponíveis

| Comando                      | Escopo                                                                                                                                                                                                      |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `pnpm test`                  | Unitários: contracts (permissões, CPF/CNPJ, contraste, schemas), API (`src/**/*.test.ts`), web (`lib/**/*.test.ts`)                                                                                         |
| `pnpm test:integration`      | `apps/api/test/**` — supertest contra a API real + PostgreSQL real                                                                                                                                          |
| `pnpm test:tenant-isolation` | `apps/api/test/isolation/**` — API e RLS com tenants A e B                                                                                                                                                  |
| `pnpm test:e2e`              | `apps/web/e2e/**` — Desktop Chrome 1366×900 e Pixel 7; API 127.0.0.1:4100 e web de produção :3100 iniciadas por `e2e/servers.ts` (build em `.next-e2e`); axe WCAG AA; Web Vitals em `.perf/web-vitals.json` |
| `pnpm perf:check`            | Baseline API p50/p95/p99 (`.perf/api-baseline.json`, não versionado)                                                                                                                                        |
| `pnpm docs:validate`         | Guias HTML (estrutura, 10 seções, sem TODO, offline) + referência no PROGRESS                                                                                                                               |
| `pnpm security:secrets`      | Padrões de segredo e arquivos proibidos                                                                                                                                                                     |
| `pnpm smoke [--api-only]`    | Health, página de login, 401 sem sessão, login/me/logout fictício (`SMOKE_URL`)                                                                                                                             |
| `pnpm verify [--e2e]`        | Gate: contratos → prettier → eslint → tsc → unit → integração → docs → segredos → build → (E2E)                                                                                                             |

## Guardas de segurança dos testes

- `NODE_ENV=test` exige banco cujo nome termina em `_test` em host local (`assertTestDatabase`), inclusive no servidor de E2E.
- `db:migrate`/`db:seed` recusam host não local fora de `NODE_ENV=production`; seed recusa produção sempre.
- Todos os dados são fictícios (domínios `.test`, CNPJ/CPF gerados por dígito verificador).

## Matriz de cobertura atual (CLAUDE.md §16.2)

| #   | Área                     | Estado                                                                                                     |
| --- | ------------------------ | ---------------------------------------------------------------------------------------------------------- |
| 1   | Qualidade de código      | PASS (lint, tipos, prettier, build); auditoria de dependências NOT RUN                                     |
| 2   | Domínio clínico          | NOT RUN (não existe ainda)                                                                                 |
| 3   | Segurança multi-tenant   | PASS — 15 testes de API/RLS + E2E de isolamento e troca de clínica na UI                                   |
| 4   | Busca avançada           | NOT RUN (Fase 2)                                                                                           |
| 5   | Documentos               | NOT RUN (Fase 1–2)                                                                                         |
| 6   | Interface desktop/mobile | PASS — 22 E2E (desktop + Pixel 7), axe WCAG 2.2 AA claro/escuro, navegação por papel                       |
| 7   | Integração               | PASS parcial — API+banco, sessão, revogação; web ⇄ API via proxy; filas/cache inexistentes                 |
| 8   | Performance              | Baseline: API p50/p95/p99; web LCP/CLS/JS de laboratório sem throttling; INP e rede móvel simulada NOT RUN |
| 9   | Resiliência e segurança  | PASS parcial — rate limit, bloqueio, limites de corpo, CSP/headers; backup/restore NOT RUN                 |
| 10  | Documentação             | PASS (`docs:validate`)                                                                                     |

## Evidências por incremento

| Data       | Escopo                         | Comando real                  | Ambiente           | Resultado              | Evidência/causa                                                                                                |
| ---------- | ------------------------------ | ----------------------------- | ------------------ | ---------------------- | -------------------------------------------------------------------------------------------------------------- |
| 2026-10-15 | Pacote inicial de documentação | —                             | —                  | NOT RUN                | Aplicação não existia                                                                                          |
| 2026-10-15 | Fundação + API                 | `pnpm verify`                 | WSL2               | PASS                   | contracts 12, api unit 5, integração 42 (isolamento 15); build OK; também em clone limpo após `pnpm bootstrap` |
| 2026-10-15 | Fundação + API                 | `pnpm perf:check`             | WSL2               | PASS (baseline)        | p95: health 2,3 ms; me 6,3 ms; units 7,4 ms; tenant 5,6 ms                                                     |
| 2026-10-15 | Web                            | `pnpm test:e2e`               | WSL2, Chromium 156 | PASS                   | 22/22; LCP login 40–88 ms, painel 96–176 ms; CLS 0; JS ~244–249 KB                                             |
| 2026-10-15 | Web                            | `pnpm smoke` (via `pnpm dev`) | WSL2               | PASS                   | health, login page, 401 sem sessão, login+me+logout pelo proxy da web                                          |
| 2026-10-15 | Web                            | `pnpm verify --e2e`           | WSL2               | ver `docs/PROGRESS.md` | gate completo com E2E                                                                                          |

## Troubleshooting

- **PostgreSQL não sobe:** ver `.data/pg.log`; porta ocupada → mudar `LOCAL_PG_PORT` e URLs do `.env`.
- **`Ignored build scripts`:** revisar `allowBuilds` em `pnpm-workspace.yaml`.
- **Docker no WSL:** opcional; habilitar em Docker Desktop › Settings › Resources › WSL integration.
- **WSL2 com `networkingMode=mirrored`:** conexão a porta _fechada_ em `127.0.0.1` fica pendurada (em `127.0.0.2`/`::1`
  é recusada na hora). Isso fazia a pré-checagem do `webServer` do Playwright levar ~5 min; por isso o E2E sobe os
  servidores em `apps/web/e2e/servers.ts` com sondagens de 1 s (suíte completa: ~33 s). Use o mesmo cuidado em
  scripts próprios (sempre timeout curto).
- **E2E porta em uso (4100/3100):** execução interrompida deixou servidor vivo; `ss -ltnp | grep 4100` e encerre o PID.
- **Chromium ausente:** `pnpm --filter @fauvovet/web exec playwright install chromium` (sem sudo no WSL Ubuntu 26.04; outras distros podem exigir `playwright install-deps`).
- **Git Bash:** se `pnpm db:start` falhar, use WSL2 e registre o erro aqui.

## Antes do deploy

`pnpm verify --e2e` + build + smoke do artefato (`SMOKE_URL=... pnpm smoke`) + backup e plano de migração/rollback +
revisão de segurança. FileZilla apenas transfere artefatos compatíveis com a hospedagem (ver `docs/DEPLOY_LOCAWEB.md`).
