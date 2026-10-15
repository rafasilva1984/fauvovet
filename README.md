# FauvoVet

Plataforma SaaS multi-tenant, open-source (MIT), para medicina veterinária e gestão de negócios pet — em
**Português do Brasil**.

> **Estado atual: Fase 0 (fundação) em andamento.** Já existem API e interface web reais com login, sessão segura,
> seleção de clínica/unidade, RBAC inicial, cadastro de clínica, unidades, equipe (leitura), aparência por tenant e
> auditoria, protegidas por Row-Level Security e testadas com dois tenants (inclusive E2E desktop/mobile). **Ainda não
> há** prontuário, agenda, pacientes ou busca clínica. Veja
> [`docs/PROGRESS.md`](docs/PROGRESS.md) e [`docs/FEATURE_PARITY.md`](docs/FEATURE_PARITY.md).

## Pré-requisitos

| Ferramenta | Versão | Observação                                                                         |
| ---------- | ------ | ---------------------------------------------------------------------------------- |
| Node.js    | 24.x   | `mise install` (usa `mise.toml`) ou `.nvmrc`                                       |
| pnpm       | 12.4.2 | `corepack enable` ou `mise install`                                                |
| Git        | 2.4x+  |                                                                                    |
| PostgreSQL | 18     | **Opcional**: por padrão o projeto baixa binários locais via npm (sem root/Docker) |

Ambientes: **WSL2/Ubuntu** (verificado), Linux (CI Ubuntu). Git Bash/macOS: suportados pelo desenho dos scripts
(Node puro, binários PostgreSQL para Windows/macOS), **ainda não verificados** — veja `docs/TESTING.md`.

## Começando

```bash
git clone https://github.com/rafasilva1984/fauvovet.git
cd fauvovet
pnpm bootstrap          # cria .env fictício, instala, inicia PostgreSQL local, migra e cria 2 tenants de teste
pnpm dev                # web em http://localhost:3000/fauvovet · API em http://127.0.0.1:4000/fauvovet/api/v1/health
```

Usuários fictícios criados pelo seed (senha = `SEED_PASSWORD` do `.env`): `ana.titular@aurora.exemplo.test`
(titular, tenant A), `bruno.vet@…`, `carla.recepcao@…`, `davi.financeiro@…`, `diana.titular@horizonte.exemplo.test`
(tenant B) e `eva.plantonista@exemplo.test` (vínculo em A e B).

Já tem PostgreSQL próprio (Docker/nativo)? Ajuste as URLs no `.env`, crie os papéis conforme
`scripts/db-local.mjs › setup` e rode `pnpm bootstrap --external-db`.

## Comandos

| Comando                                    | O que faz                                                                                 |
| ------------------------------------------ | ----------------------------------------------------------------------------------------- |
| `pnpm bootstrap`                           | Verifica ambiente, cria `.env` (sem sobrescrever), instala, prepara banco, migra e popula |
| `pnpm dev`                                 | Inicia PostgreSQL local, API (watch) e web (Next.js dev)                                  |
| `pnpm db:start` / `db:stop` / `db:status`  | Controla o PostgreSQL local em `.data/pg` (127.0.0.1:54329)                               |
| `pnpm db:setup`                            | Cria papéis `fauvovet_migrator`/`fauvovet_app` e bancos `_dev`/`_test`                    |
| `pnpm db:migrate` / `db:seed`              | Migrações versionadas / dados 100% fictícios (recusa banco não local)                     |
| `pnpm lint` / `typecheck` / `format:check` | Qualidade estática                                                                        |
| `pnpm test`                                | Testes unitários                                                                          |
| `pnpm test:integration`                    | Integração com PostgreSQL real (banco `*_test` recriado a cada execução)                  |
| `pnpm test:tenant-isolation`               | Suíte de isolamento entre tenants (API + RLS)                                             |
| `pnpm test:e2e`                            | Playwright desktop + Pixel 7 (API :4100 e web :3100 próprias, banco `_test` recriado)     |
| `pnpm perf:check`                          | Baseline de latência p50/p95/p99 (relatório em `.perf/`)                                  |
| `pnpm docs:validate`                       | Valida guias HTML de mudança e checkpoint                                                 |
| `pnpm security:secrets`                    | Varredura de segredos nos arquivos do Git                                                 |
| `pnpm verify [--e2e]`                      | **Gate obrigatório**: tudo acima + build (E2E com `--e2e`)                                |
| `pnpm build`                               | Build de produção                                                                         |
| `pnpm smoke`                               | Smoke não destrutivo contra um ambiente (`SMOKE_URL`)                                     |

Primeira execução do E2E: `pnpm --filter @fauvovet/web exec playwright install chromium`.

## Documentação

- Arquitetura: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) · Decisões: [`docs/DECISIONS.md`](docs/DECISIONS.md)
- Modelo de dados: [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) · Segurança: [`docs/SECURITY.md`](docs/SECURITY.md)
- Testes: [`docs/TESTING.md`](docs/TESTING.md) · Deploy: [`docs/DEPLOY_LOCAWEB.md`](docs/DEPLOY_LOCAWEB.md)
- Guias por mudança (HTML, abrem offline): [`docs/changes/`](docs/changes/)

## Publicação em `/fauvovet`

A aplicação usa `BASE_PATH` (padrão `/fauvovet`); a API responde em `${BASE_PATH}/api/v1`. O deploy exige servidor com
Node.js persistente e PostgreSQL — **não é compatível com hospedagem só de arquivos estáticos**. O plano Locaweb ainda
não foi verificado; veja `docs/DEPLOY_LOCAWEB.md`.

## Licença

[MIT](LICENSE). Nunca inclua dados reais de tutores/pacientes, credenciais ou `.env` no repositório.
