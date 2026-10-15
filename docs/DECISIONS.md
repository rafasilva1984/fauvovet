# Registro de decisões (ADR) — FauvoVet

Formato: contexto → decisão → consequências (benefício, custo, risco, compatibilidade Locaweb). Decisões são
reversíveis salvo indicação.

## ADR-001 — Monorepo pnpm com TypeScript estrito (2026-10-15)

- **Decisão:** pnpm workspaces (`apps/*`, `packages/*`), TypeScript 6.0.3 fixado, Node 24 (`mise.toml`, `.nvmrc`),
  `packageManager: pnpm@12.4.2`.
- **Por quê TS 6 e não 7:** `typescript-eslint` 8.71 suporta `<6.1`; o TS 7 (port nativo) ainda não é compatível com o
  ecossistema de lint. Reavaliar quando houver suporte.
- **Consequência:** sem Turborepo/Nx por ora (menos dependências); `pnpm -r` basta no tamanho atual.

## ADR-002 — NestJS 12 + SQL explícito com `pg` (sem ORM nesta fase) (2026-10-15)

- **Contexto:** o CLAUDE.md sugere Prisma ou Drizzle "após avaliar RLS e migrações".
- **Decisão:** migrações em SQL puro versionadas com checksum (`apps/api/migrations`, runner próprio de ~60 linhas) e
  consultas parametrizadas com `pg`. O contexto RLS por transação (`set_config(..., true)`) fica explícito em
  `DbService.withContext`.
- **Benefícios:** controle total de políticas RLS, FKs compostas, funções `SECURITY DEFINER` e índices de busca
  (`tsvector`, `pg_trgm`) que ORMs abstraem mal; zero mágica no caminho crítico de isolamento.
- **Custos/risco:** menos tipagem automática de linhas. Mitigação: DTOs em `packages/contracts` e testes de integração
  contra banco real. Reavaliar Drizzle (query builder tipado compatível com SQL manual) na Fase 1.

## ADR-003 — Multi-tenant em banco compartilhado com RLS (2026-10-15)

- **Decisão:** coluna `tenant_id` + `ENABLE ROW LEVEL SECURITY` com políticas `USING` e `WITH CHECK` baseadas em
  `app.current_tenant_id()`; papel da aplicação sem posse das tabelas e sem `BYPASSRLS`; papel de migração separado.
- **Por que `ENABLE` e não `FORCE`:** o dono (papel de migração) precisa executar funções `SECURITY DEFINER` estreitas
  (login, sessão, vínculos próprios) e seeds. A aplicação nunca conecta como dono — verificado por teste
  (`papel da aplicação não é superuser, não tem BYPASSRLS e não é dono`). Tabelas novas devem ter RLS — também
  verificado por teste (`todas as tabelas ... têm RLS habilitada`).
- **Identidade global:** `users` e `user_sessions` não têm `tenant_id` (uma pessoa pode atuar em várias clínicas), mas
  têm RLS: o usuário vê a si mesmo e os membros do tenant ativo; sessões só do próprio usuário.
- **Futuro:** tenants grandes podem migrar para banco dedicado mantendo a API (o contexto continua por sessão).

## ADR-004 — Sessão opaca em cookie HttpOnly + CSRF por token de sessão (2026-10-15)

- **Decisão:** token aleatório de 256 bits no cookie `fv_session` (`HttpOnly`, `SameSite=Lax`, `Secure` em produção,
  `Path=BASE_PATH`); apenas o SHA-256 é guardado. Mutações exigem `X-CSRF-Token` (token por sessão, comparação em tempo
  constante) e, se houver `Origin`, ele deve ser igual a `PUBLIC_ORIGIN`.
- **Por que não JWT:** revogação imediata (logout, bloqueio) e menor superfície.
- **Senhas:** Argon2id (`@node-rs/argon2`, binário pré-compilado, m=19 MiB, t=2, p=1 — parâmetros OWASP).
  Bloqueio após `LOGIN_MAX_FAILURES` (padrão 10) por `LOGIN_LOCK_MINUTES` (15). Mensagem idêntica para e-mail
  inexistente e senha errada, com verificação de hash fictício para equalizar tempo.
- **Pendências:** MFA para perfis privilegiados, recuperação de senha por e-mail e gestão de dispositivos (Fase 1/5).

## ADR-005 — PostgreSQL local sem root/Docker via binários `embedded-postgres` (2026-10-15)

- **Contexto:** a máquina de desenvolvimento (WSL2 Ubuntu 26.04) não tem `sudo` sem senha e o Docker Desktop não está
  integrado à distro. O CLAUDE.md prioriza Docker Compose.
- **Decisão:** `scripts/db-local.mjs` usa os binários PostgreSQL 18 do pacote npm `embedded-postgres`
  (`initdb`/`pg_ctl`) com dados em `.data/pg`, escutando só em `127.0.0.1:54329`, autenticação SCRAM. A CI usa o mesmo
  caminho. Para quem já tem PostgreSQL (Docker ou nativo): `pnpm bootstrap --external-db`.
- **Docker Compose:** adiado até haver ambiente com Docker para **testar** (não publicar configuração não verificada).
  Necessário de qualquer forma para o deploy em VPS (Fase 0/1).
- **Risco:** pacote em versão `beta` do empacotamento (o PostgreSQL em si é 18.4 estável). Uso restrito a dev/teste.

## ADR-006 — Licença MIT (2026-10-15)

- Aplicada a recomendação inicial do CLAUDE.md. **Reversível pelo proprietário** antes de contribuições externas
  relevantes. Verificar licenças de dependências e ativos visuais antes de redistribuir.

## ADR-007 — Cadastro público (onboarding) desligado por padrão (2026-10-15)

- `ALLOW_SELF_SIGNUP=false` por padrão no código; `true` no `.env.example` de desenvolvimento. Liberar em produção é
  decisão comercial (trial, verificação de e-mail, anti-abuso). Conflito de e-mail retorna mensagem genérica.

## ADR-008 — Documento (CPF/CNPJ) não é único globalmente (2026-10-15)

- Unicidade global permitiria descobrir se um CPF/CNPJ já é cliente do FauvoVet (enumeração entre tenants). Validamos
  apenas dígitos verificadores. Deduplicação comercial fica para o painel Superadmin auditado.
