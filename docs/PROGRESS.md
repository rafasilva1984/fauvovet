# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-15 22:30 (America/Sao_Paulo) · **Fase:** 1 — MVP clínico (em andamento; Fase 0 concluída exceto HTTPS/backup)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Mudança de rumo nesta sessão

O proprietário informou a hospedagem real: **Hospedagem Dedicada Locaweb 4 GB, só painel, sem SSH, banco MySQL**
(domínio `dradeboragaldino.com.br` já apontado). A stack Node/PostgreSQL não roda nesse plano. Por decisão do
proprietário, o sistema foi **reescrito em PHP 8 + Laravel 13 + MySQL** (ADR-010), seguindo o modelo do outro
projeto dele na Locaweb (Node só para build local, deploy por FTP), com isolamento entre clínicas em 5 camadas.

## Situação atual

- **Branch/remote:** `main` → `origin`. Reescrita sincronizada em `34ee508`; tutorial em `7d1546c`; deploy/domínio em `df8a793`; CI (PHP 8.3) run 38796096959: **success** (bootstrap + verify --e2e).
- **Implementado e testado (local):** login seguro (Argon2id, bloqueio, rate limit, mensagem uniforme), sessão no
  servidor, CSRF, CSP com nonce, escolha de clínica/unidade, RBAC (8 papéis), cadastro de clínica PF/PJ, unidades
  (lista/criação/detalhe), equipe (leitura), aparência por clínica (AA), auditoria somente inclusão, páginas de erro
  pt-BR, ferramentas de deploy sem SSH, empacotador para FileZilla.
- **Isolamento sem RLS:** sessão → escopo obrigatório (falha fechada) → firewall de SQL → FKs compostas → testes de
  arquitetura (`docs/SECURITY.md`).
- **Gestão de equipe (Fase 1, implementado e testado localmente; ainda não publicado):** convite por link de uso único
  (token 256 bits, só SHA-256 no banco, 7 dias, revogável, sem envio de e-mail — o titular copia/envia), aceite com conta
  nova ou existente, edição de papéis/unidades/situação, desativação imediata, regras titular × gestão, auditoria.
- **Não implementado:** prontuário, pacientes, agenda, busca; MFA, recuperação de senha, e-mail transacional; backup automático.
- **PUBLICADO (teste) na Locaweb em 15/10/2026** pelo proprietário, seguindo o tutorial: `http://dradeboragaldino.com.br/fauvovet`.
  Domínio corrigido em todo o repositório (era "gakdino"; o certo é **galdino**). `pnpm smoke` contra o servidor real:
  **5/5 PASS** (143–245 ms). **HTTPS inválido**: certificado `*.websiteseguro.com` não cobre o domínio → sem dados reais
  até o proprietário ativar SSL do domínio no painel (passos em `docs/DEPLOY_LOCAWEB.md` › HTTPS).
- **Tutorial de deploy didático:** o proprietário achou o passo a passo de `DEPLOY_LOCAWEB.md` difícil de seguir (faltava
  configuração do banco e o que copiar para onde). Reescrito como guia completo, etapas A–H, com glossário, mapa de pastas,
  folha de anotações e troubleshooting; novo comando `pnpm deploy:copiar` (pacote → `C:\fauvovet-deploy`, fora do OneDrive).
- **Último guia HTML:** [`docs/changes/2026-10-15-equipe-convites.html`](changes/2026-10-15-equipe-convites.html)

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

## Comandos executados e resultados (gestão de equipe)

- `pnpm verify --e2e` → **9/9 PASS**; PHPUnit **71/71** (440 asserções; 16 novos em `TeamManagementTest`);
  isolamento **21/21**; E2E **33 PASS + 1 ignorado de propósito** (desativar/reativar só no desktop).
- `pnpm perf:check` → p95: login 22,9 ms, painel 47,4 ms, unidades 33,5 ms, equipe 37,1 ms (local).
- Migração nova: `2026_10_15_000003_create_invitations_table` → publicação exige backup + `migrar` (guia, seção 7).

## Pendências que dependem do proprietário

1. **Ativar certificado SSL para `dradeboragaldino.com.br`** no painel (bloqueia dados reais). Depois: `.env` com
   `https://` + `SESSION_SECURE_COOKIE=true`, apagar `bootstrap/cache/config.php`, rodar smoke em HTTPS.
2. Confirmar que `DEPLOY_TOOLS_ENABLED=false` e `FAUVOVET_ALLOW_SELF_SIGNUP=false` no `.env` do servidor (smoke confirma
   que `/_deploy` responde 404 sem token).
3. Definir rotina de backup do banco pelo painel antes de dados reais.

## Riscos

- Isolamento sem RLS depende do firewall (heurístico) + escopo + FKs — risco aceito e documentado.
- Hospedagem sem backup automático confirmado — definir rotina antes de dados reais.
- `.htaccess` de produção ainda não testado no Apache real (o ensaio local não lê `.htaccess`).
- Licença MIT (ADR-006) reversível pelo proprietário.

## Próxima ação exata

1. Proprietário (opcional agora): publicar esta versão seguindo `docs/changes/2026-10-15-equipe-convites.html` › seção 7.
2. Próximo incremento: **responsáveis (tutores) e pacientes** — migração `guardians`, `patients`, `guardian_patient`
   (FKs compostas), espécies amplas incluindo exóticos, cadastro rápido, lista com busca simples por nome/telefone,
   permissões (recepção cadastra; clínico vê), testes A/B. Entrada: `database/migrations/`, `app/Models/`.
3. Cada versão nova é publicada pela seção 7 do tutorial.

## Histórico de incrementos

| Data       | Incremento                                                                                           | HTML                                                                                         | Testes                                               | Commit/branch                      | Sync GitHub            | Próximo passo                         |
| ---------- | ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------- | ---------------------------------- | ---------------------- | ------------------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação)                                                        | Não aplicável                                                                                | Não executados                                       | `0b24a5c` (main)                   | Já no remoto           | Iniciar Fase 0                        |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant                                                            | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42)                 | `7d303af` (main)                   | PASS (push confirmado) | Web: login/contexto/shell             |
| 2026-10-15 | Web: login, cadastro, contexto, shell responsivo, painel, unidades, equipe, aparência                | [2026-10-15-web-shell-login.html](changes/2026-10-15-web-shell-login.html)                   | unit 21, integração 42, E2E 22                       | main — hash na próxima atualização | push nesta sessão      | E2E na CI; Fase 1 (equipe, pacientes) |
| 2026-10-15 | **Reescrita PHP/Laravel + MySQL** (hospedagem sem SSH)                                               | [2026-10-15-reescrita-php-mysql.html](changes/2026-10-15-reescrita-php-mysql.html)           | verify --e2e 9/9 (PHPUnit 55, E2E 28)                | main — hash na próxima atualização | push nesta sessão      | 1º deploy na Locaweb                  |
| 2026-10-15 | Tutorial didático de deploy na Locaweb + `pnpm deploy:copiar`                                        | [2026-10-15-tutorial-deploy-locaweb.html](changes/2026-10-15-tutorial-deploy-locaweb.html)   | verify PASS; deploy:copiar 5 cenários                | main — hash na próxima atualização | push nesta sessão      | Proprietário executa o deploy         |
| 2026-10-15 | **Primeira publicação na Locaweb** (pelo proprietário) + correção do domínio para `dradeboragaldino` | [2026-10-15-tutorial-deploy-locaweb.html](changes/2026-10-15-tutorial-deploy-locaweb.html)   | smoke produção 5/5 PASS (HTTP); HTTPS inválido       | main                               | push nesta sessão      | SSL do domínio; Fase 1 — equipe       |
| 2026-10-15 | **Fase 1 — Gestão de equipe**: convites por link, papéis, unidades, desativação                      | [2026-10-15-equipe-convites.html](changes/2026-10-15-equipe-convites.html)                   | verify --e2e 9/9 (PHPUnit 71, isolamento 21, E2E 33) | main — hash na próxima atualização | push nesta sessão      | Responsáveis e pacientes              |
