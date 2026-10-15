# Arquitetura — FauvoVet

> Estado: **Fase 0** — stack PHP/Laravel + MySQL (ADR-010). Este documento descreve o que **existe no código**.

## Visão geral (produção na Locaweb, sem SSH)

```text
navegador ──HTTPS──▶ Apache (hospedagem) ──▶ public_html/fauvovet/index.php ──▶ app-fauvovet/ (Laravel, fora da web)
                                         └─▶ public_html/fauvovet/build/*     (CSS/JS compilados localmente)
                                                                                     │ TCP
                                                                                     ▼
                                                                      MySQL do painel (5.7+ compatível)
```

| Peça                                    | Onde                                                                        | Estado                |
| --------------------------------------- | --------------------------------------------------------------------------- | --------------------- |
| Aplicação Laravel 13 (PHP ≥ 8.3)        | `app/`, `routes/`, `config/`, `resources/views/`                            | Implementado          |
| Isolamento multi-tenant em camadas      | `app/Tenancy/`, `app/Http/Middleware/ResolveTenant.php`                     | Implementado          |
| Telas (Blade + Alpine CSP + Tailwind 4) | `resources/views`, `resources/js`, `resources/css`                          | Implementado (Fase 0) |
| Migrações MySQL 5.7+                    | `database/migrations`                                                       | Implementado          |
| Ferramentas de deploy sem SSH           | `app/Http/Controllers/DeployController.php`, `routes/deploy.php`, `deploy/` | Implementado          |
| Empacotador para FileZilla              | `scripts/deploy-package.mjs`                                                | Implementado          |
| Fila/agendador                          | driver `database` + cron do painel                                          | Não usado ainda       |
| Busca clínica (Fase 2)                  | —                                                                           | Não iniciado          |

## Ciclo de uma requisição

```text
RequestContext (request id, zera contexto de clínica)
 → [grupo web] cookies criptografados → sessão (banco) → CSRF (token + origem) → SecurityHeaders (CSP nonce)
 → auth → ResolveTenant (sessão → vínculo ativo do usuário → clínica ativa; ANTES do route model binding)
 → SubstituteBindings ({unit} já escopado) → RequirePermission (papel × permissão)
 → Controller → Models com BelongsToTenant (escopo obrigatório)
 → DB::beforeExecuting → TenantQueryFirewall (bloqueia SQL sem/de outro tenant) → MySQL (FKs compostas)
```

## Camadas de isolamento (resumo — detalhes em `docs/SECURITY.md`)

1. **Contexto só do servidor:** `TenantContext` é preenchido a partir da sessão; `tenant_id` em formulário, query ou
   cabeçalho nunca é lido.
2. **Escopo obrigatório:** `BelongsToTenant` aplica `WHERE tenant_id = :ativo` em SELECT/UPDATE/DELETE, preenche
   `tenant_id` no INSERT, recusa valor divergente e alteração de `tenant_id`. Sem contexto → `TenantContextMissing`.
3. **Firewall de SQL:** inspeciona cada SQL antes de executar; tabela de clínica sem `tenant_id`, ou filtro com
   outra clínica → `CrossTenantViolation` (403 genérico + log crítico sem dados).
4. **Banco:** `UNIQUE (tenant_id, id)` e FKs compostas `(tenant_id, x_id)`.
5. **Modo sistema** (`runAsSystem`) só para o que antecede a escolha de clínica (login, listar os próprios vínculos),
   sempre com filtro explícito por usuário. `runForTenant` para criar clínica e seeds.

## Pastas

```text
app/Tenancy/          TenantContext, TenantScope, BelongsToTenant, TenantOwned, TenantQueryFirewall, Roles
app/Http/Middleware/  RequestContext, SecurityHeaders, ResolveTenant, RequirePermission, PreventRequestForgery, DeployRateLimit
app/Http/Controllers/ Auth (login/contexto), Onboarding, Dashboard, Unit, Team, Appearance, Deploy
app/Services/         ContextService (vínculos e troca de clínica)
app/Support/          Audit, Contrast (WCAG), BrazilianDocument (CPF/CNPJ), LayoutData, RequestId
database/             migrations (MySQL 5.7+), seeders (dados fictícios)
resources/views/      layouts (base/auth/app), components (UI acessível), páginas, errors (pt-BR)
deploy/               index.php e .htaccess de produção, .env de produção (modelo), proteção de app-fauvovet
scripts/              local-mysql, php, dev, bootstrap, verify, smoke, perf, e2e-server, deploy-package/token/remote, docs-validate, secrets-scan
tests/                PHPUnit: Unit, Feature, Feature/Isolation (grupo tenant-isolation)
e2e/                  Playwright (desktop + Pixel 7) contra o servidor em /fauvovet
```

## Base path `/fauvovet`

O subdiretório vem do servidor (`SCRIPT_NAME`): Laravel gera URLs e assets relativos a ele. `APP_URL` deve conter
`/fauvovet`; o cookie usa `SESSION_PATH=/fauvovet`. Hospedar na raiz ou em subdomínio = ajustar `APP_URL`,
`SESSION_PATH` e a pasta pública, sem alterar código. Localmente, `scripts/dev-router.php` reproduz o subdiretório.

Decisões: `docs/DECISIONS.md`. Modelo de dados: `docs/DATA_MODEL.md`. Deploy: `docs/DEPLOY_LOCAWEB.md`.
