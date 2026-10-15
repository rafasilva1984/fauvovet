# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-15 19:40 (America/Sao_Paulo) · **Fase:** 0 — Fundação (em andamento)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Mudança de rumo nesta sessão

O proprietário informou a hospedagem real: **Hospedagem Dedicada Locaweb 4 GB, só painel, sem SSH, banco MySQL**
(domínio `dradeboragakdino.com.br` já apontado). A stack Node/PostgreSQL não roda nesse plano. Por decisão do
proprietário, o sistema foi **reescrito em PHP 8 + Laravel 13 + MySQL** (ADR-010), seguindo o modelo do outro
projeto dele na Locaweb (Node só para build local, deploy por FTP), com isolamento entre clínicas em 5 camadas.

## Situação atual

- **Branch/remote:** `main` → `origin`. Reescrita sincronizada em `34ee508`; checkpoint em `9bb7d11`; CI (PHP 8.3) run 38796096959: **success** (bootstrap + verify --e2e).
- **Implementado e testado (local):** login seguro (Argon2id, bloqueio, rate limit, mensagem uniforme), sessão no
  servidor, CSRF, CSP com nonce, escolha de clínica/unidade, RBAC (8 papéis), cadastro de clínica PF/PJ, unidades
  (lista/criação/detalhe), equipe (leitura), aparência por clínica (AA), auditoria somente inclusão, páginas de erro
  pt-BR, ferramentas de deploy sem SSH, empacotador para FileZilla.
- **Isolamento sem RLS:** sessão → escopo obrigatório (falha fechada) → firewall de SQL → FKs compostas → testes de
  arquitetura (`docs/SECURITY.md`).
- **Não implementado:** prontuário, pacientes, agenda, busca; MFA, recuperação de senha, convites; backup automático.
- **Não publicado:** nenhum arquivo foi enviado à Locaweb.
- **Tutorial de deploy didático:** o proprietário achou o passo a passo de `DEPLOY_LOCAWEB.md` difícil de seguir (faltava
  configuração do banco e o que copiar para onde). Reescrito como guia completo, etapas A–H, com glossário, mapa de pastas,
  folha de anotações e troubleshooting; novo comando `pnpm deploy:copiar` (pacote → `C:\fauvovet-deploy`, fora do OneDrive).
- **Último guia HTML:** [`docs/changes/2026-10-15-tutorial-deploy-locaweb.html`](changes/2026-10-15-tutorial-deploy-locaweb.html)

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

O proprietário executa o tutorial [`2026-10-15-tutorial-deploy-locaweb.html`](changes/2026-10-15-tutorial-deploy-locaweb.html)
(a folha de anotações cobre PHP ≥ 8.3, dados do MySQL, HTTPS e SFTP/FTPS; senhas ficam só no `.env` do servidor) e
informa o resultado das etapas F2 (diagnóstico) e H1 (smoke).

## Riscos

- Isolamento sem RLS depende do firewall (heurístico) + escopo + FKs — risco aceito e documentado.
- Hospedagem sem backup automático confirmado — definir rotina antes de dados reais.
- `.htaccess` de produção ainda não testado no Apache real (o ensaio local não lê `.htaccess`).
- Licença MIT (ADR-006) reversível pelo proprietário.

## Próxima ação exata

1. Proprietário: seguir `docs/changes/2026-10-15-tutorial-deploy-locaweb.html` (etapas A–H).
2. Com o retorno de F2/H1: registrar o deploy aqui e corrigir o que o servidor real revelar (`.htaccess` no Apache,
   `open_basedir`, permissões, versão do MySQL).
3. Depois: Fase 1 — gestão de equipe (convites/papéis) e migração de responsáveis/pacientes.

## Histórico de incrementos

| Data       | Incremento                                                                            | HTML                                                                                         | Testes                                | Commit/branch                      | Sync GitHub            | Próximo passo                         |
| ---------- | ------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------- | ---------------------------------- | ---------------------- | ------------------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação)                                         | Não aplicável                                                                                | Não executados                        | `0b24a5c` (main)                   | Já no remoto           | Iniciar Fase 0                        |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant                                             | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42)  | `7d303af` (main)                   | PASS (push confirmado) | Web: login/contexto/shell             |
| 2026-10-15 | Web: login, cadastro, contexto, shell responsivo, painel, unidades, equipe, aparência | [2026-10-15-web-shell-login.html](changes/2026-10-15-web-shell-login.html)                   | unit 21, integração 42, E2E 22        | main — hash na próxima atualização | push nesta sessão      | E2E na CI; Fase 1 (equipe, pacientes) |
| 2026-10-15 | **Reescrita PHP/Laravel + MySQL** (hospedagem sem SSH)                                | [2026-10-15-reescrita-php-mysql.html](changes/2026-10-15-reescrita-php-mysql.html)           | verify --e2e 9/9 (PHPUnit 55, E2E 28) | main — hash na próxima atualização | push nesta sessão      | 1º deploy na Locaweb                  |
| 2026-10-15 | Tutorial didático de deploy na Locaweb + `pnpm deploy:copiar`                         | [2026-10-15-tutorial-deploy-locaweb.html](changes/2026-10-15-tutorial-deploy-locaweb.html)   | verify PASS; deploy:copiar 5 cenários | main — hash na próxima atualização | push nesta sessão      | Proprietário executa o deploy         |
