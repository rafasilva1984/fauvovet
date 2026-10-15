# FauvoVet

Plataforma SaaS multi-tenant, open-source (MIT), para medicina veterinária e gestão de negócios pet — em
**Português do Brasil**.

> **Estado atual: Fase 0 (fundação).** Aplicação real em **PHP 8 + Laravel 13 + MySQL**, feita para rodar em
> hospedagem compartilhada/dedicada **sem SSH** (Locaweb) e publicada por FileZilla. Já funcionam: login seguro,
> escolha de clínica/unidade, papéis e permissões, cadastro de clínica, unidades, equipe (leitura), aparência por
> clínica e auditoria, com isolamento entre clínicas em cinco camadas testadas. **Ainda não há** prontuário, agenda,
> pacientes ou busca clínica. Veja [`docs/PROGRESS.md`](docs/PROGRESS.md).

## Pré-requisitos (desenvolvimento)

| Ferramenta     | Versão                                                                                         | Observação                                                                             |
| -------------- | ---------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| PHP            | 8.3+ (com `mbstring`, `intl`, `sodium`; `pdo_mysql` é obtido automaticamente no Ubuntu/Debian) | WSL: `sudo apt install php8.x-cli php8.x-mysql php8.x-intl php8.x-mbstring php8.x-xml` |
| Composer       | 2.x                                                                                            | https://getcomposer.org                                                                |
| Node.js + pnpm | 24 + 12.4.2                                                                                    | só para compilar CSS/JS e rodar testes E2E (`mise install`)                            |
| MySQL          | 5.7+                                                                                           | **opcional**: `pnpm bootstrap` baixa Percona 5.7 local, sem root (Linux x86_64)        |

Ambientes: **WSL2/Ubuntu** (verificado) e CI Ubuntu com PHP 8.3. Git Bash/macOS: use MySQL próprio
(`pnpm bootstrap --external-db`); não verificados ainda.

## Começando

```bash
git clone https://github.com/rafasilva1984/fauvovet.git
cd fauvovet
pnpm bootstrap     # .env fictício, dependências, MySQL local, migrações, 2 clínicas fictícias, build
pnpm dev           # http://127.0.0.1:8000/fauvovet
```

Usuários **fictícios** (senha = `SEED_PASSWORD` do `.env`): `ana.titular@aurora.exemplo.test` (titular, clínica A,
2 unidades), `bruno.vet@…`, `carla.recepcao@…`, `davi.financeiro@…` (clínica A), `diana.titular@horizonte.exemplo.test`
(clínica B) e `eva.plantonista@exemplo.test` (vínculo nas duas).

## Comandos

| Comando                                                                | O que faz                                                                      |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `pnpm bootstrap [--external-db]`                                       | Prepara tudo (idempotente; nunca sobrescreve `.env`)                           |
| `pnpm dev`                                                             | MySQL local + build em modo watch + PHP servindo em `/fauvovet`                |
| `pnpm db:setup` / `db:start` / `db:stop` / `db:status`                 | MySQL local (Percona 5.7, `.data/mysql`, 127.0.0.1:33079)                      |
| `pnpm db:migrate` / `db:seed`                                          | Migrações / dados 100% fictícios (seed recusa produção)                        |
| `pnpm lint` · `pnpm format` · `pnpm analyse`                           | Pint + Prettier · formatar · PHPStan nível 6                                   |
| `pnpm test`                                                            | PHPUnit: unidade, integração e isolamento, contra MySQL real (`fauvovet_test`) |
| `pnpm test:tenant-isolation`                                           | Só a suíte de isolamento entre clínicas                                        |
| `pnpm test:e2e`                                                        | Playwright desktop + celular contra o servidor em `/fauvovet`                  |
| `pnpm perf:check`                                                      | Baseline p50/p95/p99 (`.perf/`)                                                |
| `pnpm docs:validate` · `pnpm security:secrets` · `pnpm security:audit` | Guias HTML · segredos · `composer audit`                                       |
| `pnpm verify [--e2e]`                                                  | **Gate obrigatório** antes de push/deploy                                      |
| `pnpm build`                                                           | Compila CSS/JS em `public/build`                                               |
| `pnpm deploy:package` · `deploy:token` · `deploy:remote`               | Pacote para FileZilla · token de deploy · migrar/diagnosticar sem SSH          |
| `pnpm smoke`                                                           | Verificação não destrutiva de um ambiente (`SMOKE_URL`)                        |

## Documentação

Arquitetura [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) · Decisões [`docs/DECISIONS.md`](docs/DECISIONS.md) ·
Segurança [`docs/SECURITY.md`](docs/SECURITY.md) · Dados [`docs/DATA_MODEL.md`](docs/DATA_MODEL.md) ·
Testes [`docs/TESTING.md`](docs/TESTING.md) · **Deploy** [`docs/DEPLOY_LOCAWEB.md`](docs/DEPLOY_LOCAWEB.md) ·
Guias por mudança (HTML, abrem offline) [`docs/changes/`](docs/changes/).

## Licença

[MIT](LICENSE). Nunca inclua dados reais de tutores/pacientes, credenciais ou `.env` no repositório.
