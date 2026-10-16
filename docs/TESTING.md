# FauvoVet — Estratégia e evidências de testes

## Ambientes

| Ambiente                          | Estado         | Observações                                                                                                                                                  |
| --------------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| WSL2 Ubuntu 26.04 (x86_64)        | **Verificado** | PHP 8.5.4 (CLI do sistema, `pdo_mysql` extraído sem root), Composer 2.10.3, Node 24.21, pnpm 12.4.2, Percona Server 5.7.44 local, Chromium (Playwright 1.64) |
| CI GitHub Actions `ubuntu-latest` | Configurado    | **PHP 8.3** (mínimo suportado) + mesmo fluxo: `pnpm bootstrap` + `pnpm verify --e2e`                                                                         |
| Locaweb (produção)                | **NOT RUN**    | ensaio fiel feito localmente (simulação de produção, ver guia HTML 2026-10-15-reescrita-php-mysql)                                                           |
| Git Bash / macOS                  | NOT RUN        | usar MySQL próprio (`--external-db`)                                                                                                                         |

Bancos locais: `fauvovet_dev` (dev, seed fictício) e `fauvovet_test` (recriado por `pnpm test`, `test:e2e` e `perf:check`).

## Comandos e escopo

| Comando                      | Escopo                                                                                                                                                                                      |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `pnpm test`                  | PHPUnit: `tests/Unit` (papéis, CPF/CNPJ, contraste) e `tests/Feature` (auth, cadastro, RBAC, deploy/cabeçalhos, **isolamento**) contra MySQL real                                           |
| `pnpm test:tenant-isolation` | `tests/Feature/Isolation` — 5 barreiras de isolamento, 2 clínicas                                                                                                                           |
| `pnpm test:e2e`              | `e2e/` — Desktop Chrome 1366×900 e Pixel 7; servidor PHP em `/fauvovet` com sessão em banco; axe WCAG 2.2 AA (claro/escuro); erros de CSP no console; cookie real; 419 sem CSRF; Web Vitals |
| `pnpm analyse`               | PHPStan/Larastan nível 6                                                                                                                                                                    |
| `pnpm perf:check`            | p50/p95/p99 de login/painel/unidades/equipe/pacientes/buscas (PHP embutido com opcache). `PERF_VOLUME=20000` acrescenta pacientes sintéticos na clínica B (só banco `_test`)                |
| `pnpm verify [--e2e]`        | Pint → Prettier → PHPStan → PHPUnit → composer audit → docs → segredos → build → (E2E)                                                                                                      |

## Guardas

- PHPUnit/E2E/perf recusam banco que não seja local e terminado em `_test`.
- Seed recusa `APP_ENV=production`. Dados 100% fictícios (domínios `.test`, CPF/CNPJ gerados por dígito verificador).

## Matriz de cobertura (CLAUDE.md §16.2)

| #   | Área                        | Estado                                                                                     |
| --- | --------------------------- | ------------------------------------------------------------------------------------------ |
| 1   | Qualidade de código         | PASS — Pint, Prettier, PHPStan 6, build, `composer audit`                                  |
| 2   | Domínio clínico             | NOT RUN (não existe ainda)                                                                 |
| 3   | Segurança multi-tenant      | PASS — suíte de isolamento (HTTP, escopo, firewall, FK composta, arquitetura) + E2E        |
| 4   | Busca avançada              | NOT RUN (Fase 2)                                                                           |
| 5   | Documentos                  | NOT RUN (Fase 1–2)                                                                         |
| 6   | Interface desktop/mobile    | PASS — E2E desktop + Pixel 7, axe AA claro/escuro                                          |
| 7   | Integração                  | PASS parcial — sessão em banco, migração/caches via HTTP, empacotamento; fila/cron sem uso |
| 8   | Performance                 | Baseline coletada (servidor local); hospedagem real NOT RUN                                |
| 9   | Resiliência e segurança     | PASS parcial — rate limits, bloqueio, CSP, deploy com banco vazio; backup/restore NOT RUN  |
| 10  | Documentação e distribuição | PASS (`docs:validate`), pacote de deploy com manifesto SHA-256                             |

## Evidências por incremento

| Data       | Escopo                                 | Comando                                                             | Ambiente | Resultado | Evidência                         |
| ---------- | -------------------------------------- | ------------------------------------------------------------------- | -------- | --------- | --------------------------------- |
| 2026-10-15 | Fundação Node/PostgreSQL (substituída) | `pnpm verify --e2e`                                                 | WSL2     | PASS      | histórico: guias HTML anteriores  |
| 2026-10-15 | Reescrita PHP/MySQL                    | ver `docs/PROGRESS.md` e guia `2026-10-15-reescrita-php-mysql.html` | WSL2     | ver guia  | números reais registrados no guia |

## Troubleshooting

- **`pdo_mysql` ausente:** `pnpm db:setup` extrai do pacote do sistema (Ubuntu/Debian); fora disso instale `php8.x-mysql`.
- **MySQL local não sobe:** `.data/mysql.log`; porta 33079 ocupada → altere `DB_PORT` no `.env`.
- **WSL2 com `networkingMode=mirrored`:** conexão a porta _fechada_ em 127.0.0.1 trava em vez de ser recusada; todos os
  scripts usam timeouts curtos (≤ 1 s) ao sondar portas.
- **E2E "Já existe um servidor":** encerre o processo na porta 8100 (`ss -ltnp | grep 8100`).
- **Chromium ausente:** `pnpm exec playwright install chromium`.
