# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-15 16:45 (America/Sao_Paulo) · **Fase:** 0 — Fundação (em andamento)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Mudança de rumo nesta sessão

O proprietário informou a hospedagem real: **Hospedagem Dedicada Locaweb 4 GB, só painel, sem SSH, banco MySQL**
(domínio `dradeboragakdino.com.br` já apontado). A stack Node/PostgreSQL não roda nesse plano. Por decisão do
proprietário, o sistema foi **reescrito em PHP 8 + Laravel 13 + MySQL** (ADR-010), seguindo o modelo do outro
projeto dele na Locaweb (Node só para build local, deploy por FTP), com isolamento entre clínicas em 5 camadas.

## Situação atual

- **Branch/remote:** `main` → `origin`. Reescrita sincronizada em `34ee508`; CI (PHP 8.3) run 38796096959: **success** (bootstrap + verify --e2e).
- **Implementado e testado (local):** login seguro (Argon2id, bloqueio, rate limit, mensagem uniforme), sessão no
  servidor, CSRF, CSP com nonce, escolha de clínica/unidade, RBAC (8 papéis), cadastro de clínica PF/PJ, unidades
  (lista/criação/detalhe), equipe (leitura), aparência por clínica (AA), auditoria somente inclusão, páginas de erro
  pt-BR, ferramentas de deploy sem SSH, empacotador para FileZilla.
- **Isolamento sem RLS:** sessão → escopo obrigatório (falha fechada) → firewall de SQL → FKs compostas → testes de
  arquitetura (`docs/SECURITY.md`).
- **Não implementado:** prontuário, pacientes, agenda, busca; MFA, recuperação de senha, convites; backup automático.
- **Não publicado:** nenhum arquivo foi enviado à Locaweb.
- **Último guia HTML:** [`docs/changes/2026-10-15-reescrita-php-mysql.html`](changes/2026-10-15-reescrita-php-mysql.html)

## Ambiente (verificado)

WSL2 Ubuntu 26.04 · PHP 8.5.4 (sistema; `pdo_mysql` extraído sem root em `.data/php`) · Composer 2.10.3 ·
Node 24.21 + pnpm 12.4.2 · Percona Server 5.7.44 local (`.data/mysql`, 127.0.0.1:33079) · Chromium (Playwright 1.64).
Sem sudo. O PostgreSQL local antigo (`.data/pg`) não é mais usado.

## Comandos executados e resultados (reescrita)

- `pnpm db:setup` (Percona baixado com SHA-256 conferido), `migrate`, `db:seed` → OK.
- `pnpm verify --e2e` → **9/9 PASS**: Pint, Prettier, PHPStan 6, PHPUnit **55/55** (isolamento 19), `composer audit`,
  docs, segredos, build, **E2E 28/28**.
- `pnpm perf:check` → p95: login 22 ms, painel 37 ms, unidades 36 ms, equipe 35 ms (servidor local, não Locaweb).
- Ensaio de produção com o pacote real: diagnóstico → migrar → otimizar → cadastro → `pnpm smoke` 6/6 PASS.

## Pendências que dependem do proprietário (bloqueiam o deploy)

1. Versão do PHP selecionada no painel (precisa ser **≥ 8.3**).
2. Host, porta, nome do banco e usuário do MySQL criado no painel (a senha fica só no `.env` do servidor).
3. O domínio tem **HTTPS** ativo? (obrigatório antes de dados reais).
4. Protocolo disponível para o FileZilla (SFTP ou FTPS).

## Riscos

- Isolamento sem RLS depende do firewall (heurístico) + escopo + FKs — risco aceito e documentado.
- Hospedagem sem backup automático confirmado — definir rotina antes de dados reais.
- `.htaccess` de produção ainda não testado no Apache real (o ensaio local não lê `.htaccess`).
- Licença MIT (ADR-006) reversível pelo proprietário.

## Próxima ação exata

1. Receber os 4 itens acima.
2. `git pull --ff-only && pnpm db:start && pnpm verify --e2e && pnpm deploy:package`.
3. Seguir `docs/DEPLOY_LOCAWEB.md` › Primeiro deploy (passos 1–17), registrar resultados aqui e num novo guia HTML.
4. Depois: Fase 1 — gestão de equipe (convites/papéis) e migração de responsáveis/pacientes.

## Histórico de incrementos

| Data       | Incremento                                                                            | HTML                                                                                         | Testes                                | Commit/branch                      | Sync GitHub            | Próximo passo                         |
| ---------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------- | ---------------------------------- | ---------------------- | ------------------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação)                                         | Não aplicável                                                                                | Não executados                        | `0b24a5c` (main)                   | Já no remoto           | Iniciar Fase 0                        |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant                                             | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42)  | `7d303af` (main)                   | PASS (push confirmado) | Web: login/contexto/shell             |
| 2026-10-15 | Web: login, cadastro, contexto, shell responsivo, painel, unidades, equipe, aparência | [2026-10-15-web-shell-login.html](changes/2026-10-15-web-shell-login.html)                   | unit 21, integração 42, E2E 22        | main — hash na próxima atualização | push nesta sessão      | E2E na CI; Fase 1 (equipe, pacientes) |
| 2026-10-15 | **Reescrita PHP/Laravel + MySQL** (hospedagem sem SSH)                                | [2026-10-15-reescrita-php-mysql.html](changes/2026-10-15-reescrita-php-mysql.html)           | verify --e2e 9/9 (PHPUnit 55, E2E 28) | main — hash na próxima atualização | push nesta sessão      | 1º deploy na Locaweb                  |
