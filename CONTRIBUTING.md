# Contribuindo com o FauvoVet

1. Branch descritiva (`feat/agenda-semana`, `fix/firewall-join`), `pnpm bootstrap`, `pnpm dev`.
2. Cada incremento traz: testes (incluindo **isolamento entre duas clínicas** quando tocar dados), migração nova
   (nunca editar migração já aplicada), guia HTML em `docs/changes/AAAA-MM-DD-slug.html` (a partir do `TEMPLATE.html`)
   e atualização de `docs/PROGRESS.md`.
3. `pnpm verify --e2e` precisa terminar com tudo `PASS` antes do push.

## Regras para dados de clínica (obrigatórias)

- Tabela de clínica: coluna `tenant_id`, `UNIQUE (tenant_id, id)`, FKs compostas `(tenant_id, x_id)` para outras
  tabelas de clínica, e registro em `TenantQueryFirewall::TENANT_TABLES` (um teste falha se faltar).
- Model: `use BelongsToTenant` + `implements TenantOwned`. Nunca aceite `tenant_id` vindo da requisição.
- Evite SQL bruto e `withoutGlobalScopes()` em tabelas de clínica; o firewall bloqueia, e exceções exigem revisão.
- `runAsSystem()` só antes da escolha de clínica e sempre com filtro explícito (ex.: `user_id` do usuário autenticado).
- Permissões no backend (`permission:` nas rotas); a interface só reflete.
- Compatível com **MySQL 5.7** (sem CHECK, CTE, funções de janela, colunas geradas) e **PHP 8.3**.

## Convenções

Mensagens em pt-BR; código em inglês. Dados de teste 100% fictícios (domínios `.test`). Scripts em Node
(`scripts/*.mjs`) para funcionar em WSL/Linux/macOS. Finais de linha LF.

## Segurança

Vulnerabilidades: _security advisory_ privada no GitHub (veja `docs/SECURITY.md`).
