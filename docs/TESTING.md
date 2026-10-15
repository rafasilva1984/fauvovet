# FauvoVet — Estratégia e evidências de testes

## Ambientes

| Ambiente                                | Estado         | Observações                                                                                                                 |
| --------------------------------------- | -------------- | --------------------------------------------------------------------------------------------------------------------------- |
| WSL2 Ubuntu 26.04 (kernel 6.18, x86_64) | **Verificado** | Node 24.21.0, pnpm 12.4.2, Git 2.53.0, mise 2026.9.11; sem sudo; Docker Desktop instalado no Windows mas sem integração WSL |
| CI GitHub Actions `ubuntu-latest`       | Configurado    | Mesmo fluxo: `pnpm bootstrap` + `pnpm verify`                                                                               |
| Git Bash (Windows)                      | **NOT RUN**    | Scripts em Node puro e binário PostgreSQL Windows previstos, mas não executados                                             |
| macOS                                   | NOT RUN        | —                                                                                                                           |

Bancos: `fauvovet_dev` (desenvolvimento, seed fictício) e `fauvovet_test` (schema recriado a cada `test:integration`),
no PostgreSQL 18.4 local em `127.0.0.1:54329`. Papéis: `fauvovet_migrator` (dono) e `fauvovet_app` (RLS aplicada).

## Comandos reais disponíveis

| Comando                      | Escopo                                                                                                               |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `pnpm test`                  | Unitários: `packages/contracts` (permissões, CPF/CNPJ, contraste, schemas) e `apps/api/src/**/*.test.ts`             |
| `pnpm test:integration`      | `apps/api/test/**` — supertest contra a API real + PostgreSQL real                                                   |
| `pnpm test:tenant-isolation` | `apps/api/test/isolation/**` — API e RLS com tenants A e B                                                           |
| `pnpm perf:check`            | Baseline p50/p95/p99 (`.perf/api-baseline.json`, não versionado)                                                     |
| `pnpm docs:validate`         | Guias HTML (estrutura, 10 seções, sem TODO, offline) + referência no PROGRESS                                        |
| `pnpm security:secrets`      | Padrões de segredo e arquivos proibidos                                                                              |
| `pnpm verify`                | Gate: contratos → prettier → eslint → tsc → unit → integração → docs → segredos → build (`--e2e` quando existir web) |

## Guardas de segurança dos testes

- `NODE_ENV=test` exige banco cujo nome termina em `_test` em host local (`assertTestDatabase`).
- `db:migrate`/`db:seed` recusam host não local fora de `NODE_ENV=production`; seed recusa produção sempre.
- Todos os dados são fictícios (domínios `.test`, CNPJ gerado por dígito verificador).

## Matriz de cobertura atual (CLAUDE.md §16.2)

| #   | Área                     | Estado                                                                                                  |
| --- | ------------------------ | ------------------------------------------------------------------------------------------------------- |
| 1   | Qualidade de código      | PASS (lint, tipos, prettier, build); auditoria de dependências NOT RUN                                  |
| 2   | Domínio clínico          | NOT RUN (não existe ainda)                                                                              |
| 3   | Segurança multi-tenant   | PASS — 15 testes (IDs previsíveis, tenant_id manipulado, troca de contexto, RLS SQL, FK composta, pool) |
| 4   | Busca avançada           | NOT RUN (Fase 2)                                                                                        |
| 5   | Documentos               | NOT RUN (Fase 1–2)                                                                                      |
| 6   | Interface desktop/mobile | NOT RUN (web no próximo incremento)                                                                     |
| 7   | Integração               | PASS parcial — API+banco, sessão, expiração/revogação; filas/cache inexistentes                         |
| 8   | Performance              | Baseline coletada (API); web vitals NOT RUN                                                             |
| 9   | Resiliência e segurança  | PASS parcial — rate limit, bloqueio, limites de corpo; backup/restore NOT RUN                           |
| 10  | Documentação             | PASS (`docs:validate`)                                                                                  |

## Evidências por incremento

| Data       | Escopo                         | Comando real      | Ambiente | Resultado       | Evidência/causa                                                   |
| ---------- | ------------------------------ | ----------------- | -------- | --------------- | ----------------------------------------------------------------- |
| 2026-10-15 | Pacote inicial de documentação | —                 | —        | NOT RUN         | Aplicação não existia                                             |
| 2026-10-15 | Fundação + API multi-tenant    | `pnpm verify`     | WSL2     | PASS            | contracts 12, api unit 5, integração 42 (isolamento 15); build OK |
| 2026-10-15 | Fundação + API multi-tenant    | `pnpm perf:check` | WSL2     | PASS (baseline) | p95: health 2,3 ms; me 6,3 ms; units 7,4 ms; tenant 5,6 ms        |

## Troubleshooting

- **PostgreSQL não sobe:** ver `.data/pg.log`; porta ocupada → mudar `LOCAL_PG_PORT` e URLs do `.env`.
- **`Ignored build scripts`:** revisar `allowBuilds` em `pnpm-workspace.yaml`.
- **Docker no WSL:** opcional; habilitar em Docker Desktop › Settings › Resources › WSL integration.
- **Git Bash:** se `pnpm db:start` falhar, use WSL2 e registre o erro aqui.

## Antes do deploy

`pnpm verify` + build + smoke do artefato (`SMOKE_URL=... pnpm smoke`) + backup e plano de migração/rollback + revisão de
segurança. FileZilla apenas transfere artefatos compatíveis com a hospedagem (ver `docs/DEPLOY_LOCAWEB.md`).
