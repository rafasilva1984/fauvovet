# FauvoVet — Checkpoint e ponto de retomada

> **Atualizado em:** 2026-10-17 01:00 (America/Sao_Paulo) · **Fase:** 1 — MVP clínico (em andamento; Fase 0 concluída exceto HTTPS/backup)
> **Regra:** atualizar a cada incremento, bloqueio e antes de encerrar qualquer sessão.

## Mudança de rumo nesta sessão

O proprietário informou a hospedagem real: **Hospedagem Dedicada Locaweb 4 GB, só painel, sem SSH, banco MySQL**
(domínio `dradeboragaldino.com.br` já apontado). A stack Node/PostgreSQL não roda nesse plano. Por decisão do
proprietário, o sistema foi **reescrito em PHP 8 + Laravel 13 + MySQL** (ADR-010), seguindo o modelo do outro
projeto dele na Locaweb (Node só para build local, deploy por FTP), com isolamento entre clínicas em 5 camadas.

## Situação atual

- **Branch/remote:** `main` → `origin`. Reescrita sincronizada em `34ee508`; tutorial em `7d1546c`; deploy/domínio em `df8a793`; equipe em `5bc5577`; pacientes em `7aee4ec`; agenda em `8f91a50`; visual em `1dacafc`; ajustes de cadastro em `57a5c46` (CI success); CI (PHP 8.3) run 38796096959: **success** (bootstrap + verify --e2e).
- **Implementado e testado (local):** login seguro (Argon2id, bloqueio, rate limit, mensagem uniforme), sessão no
  servidor, CSRF, CSP com nonce, escolha de clínica/unidade, RBAC (8 papéis), cadastro de clínica PF/PJ, unidades
  (lista/criação/detalhe), equipe (leitura), aparência por clínica (AA), auditoria somente inclusão, páginas de erro
  pt-BR, ferramentas de deploy sem SSH, empacotador para FileZilla.
- **Isolamento sem RLS:** sessão → escopo obrigatório (falha fechada) → firewall de SQL → FKs compostas → testes de
  arquitetura (`docs/SECURITY.md`).
- **Gestão de equipe (Fase 1): PUBLICADA na Locaweb pelo proprietário** (smoke 5/5 + página de convite no ar): convite por link de uso único
  (token 256 bits, só SHA-256 no banco, 7 dias, revogável, sem envio de e-mail — o titular copia/envia), aceite com conta
  nova ou existente, edição de papéis/unidades/situação, desativação imediata, regras titular × gestão, auditoria.
- **Responsáveis e pacientes (Fase 1): PUBLICADO na Locaweb pelo proprietário** (`/pacientes` respondendo, smoke 5/5): cadastro rápido
  paciente+responsável, 16 espécies + detalhe livre (exóticos), idade aproximada → data estimada, vínculos N:N com FK
  composta, busca por nome/telefone/microchip, aviso de duplicado (só na clínica), óbito/arquivamento, CPF mascarado
  sem permissão de escrita, auditoria. Busca de cadastro, NÃO a busca clínica da Fase 2.
- **Agenda (Fase 1): PUBLICADA na Locaweb** (`/agenda` respondendo no servidor): dia/semana por unidade e
  profissional, fuso da unidade (UTC no banco, ADR-011), 6 tipos padrão configuráveis, 7 status com transições
  validadas, conflito por profissional (serializado com lock) e encaixe, fila de espera com minutos, reagendar,
  iniciar/concluir só para quem atende, painel "hoje", agenda na ficha do paciente.
- **Novo visual (design 2.0): PUBLICADO** pelo proprietário (pacote `1dacafc` gerado): pedido do proprietário
  ("telas sem vida, quadradas"). Só design: fonte Plus Jakarta Sans auto-hospedada, menu lateral escuro em grupos,
  barra superior com busca rápida e "Novo", painel com faixa em degradê e indicadores, agenda em linha do tempo,
  avatares por espécie, cabeçalhos de perfil, login com prévia ilustrativa, modo escuro refeito (ADR-012).
- **Ajustes pedidos + Prontuário (Fase 1, implementado e testado localmente; ainda não publicado):** sem "(opcional)",
  cor sem código, idade pela data, CEP→endereço (ViaCEP pelo servidor) + número/complemento/bairro, UF em lista;
  prontuário com rascunho → finalizar e assinar (só veterinário) → bloqueio + adendos, sinais vitais, hipóteses ×
  diagnósticos, alertas clínicos, peso com gráfico, auditoria de acesso, "Iniciar atendimento" abre o prontuário
  (ADR-013).
- **Não implementado:** receitas/documentos PDF, anexos (alergias, peso, vacinas), busca clínica, lembretes, visão mensal/salas/recorrência; MFA, recuperação de senha, e-mail transacional; backup automático.
- **PUBLICADO (teste) na Locaweb em 15/10/2026** pelo proprietário, seguindo o tutorial: `http://dradeboragaldino.com.br/fauvovet`.
  Domínio corrigido em todo o repositório (era "gakdino"; o certo é **galdino**). `pnpm smoke` contra o servidor real:
  **5/5 PASS** (143–245 ms). **HTTPS inválido**: certificado `*.websiteseguro.com` não cobre o domínio → sem dados reais
  até o proprietário ativar SSL do domínio no painel (passos em `docs/DEPLOY_LOCAWEB.md` › HTTPS).
- **Tutorial de deploy didático:** o proprietário achou o passo a passo de `DEPLOY_LOCAWEB.md` difícil de seguir (faltava
  configuração do banco e o que copiar para onde). Reescrito como guia completo, etapas A–H, com glossário, mapa de pastas,
  folha de anotações e troubleshooting; novo comando `pnpm deploy:copiar` (pacote → `C:\fauvovet-deploy`, fora do OneDrive).
- **Último guia HTML:** [`docs/changes/2026-10-17-prontuario.html`](changes/2026-10-17-prontuario.html)

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

## Comandos executados e resultados (responsáveis e pacientes)

- `pnpm verify --e2e` → **9/9 PASS**; PHPUnit **85/85** (622 asserções; 14 novos em `PatientRegistryTest`);
  isolamento **25/25**; E2E **39 PASS + 1 ignorado de propósito**; axe sem violações nas 8 telas novas.
- `pnpm perf:check` (seed) → p95 pacientes 39,7 ms; buscas ~34 ms. `PERF_VOLUME=20000` → p95 /pacientes 74,9 ms,
  buscas 176–205 ms. A 1ª versão da busca (EXISTS correlacionado) estourava o tempo com 20 mil — corrigida (2 passos +
  índice `(tenant_id, status, name)`).
- Migração nova: `2026_10_15_000004_create_guardians_and_patients_tables` → publicação exige backup + `migrar`.

## Comandos executados e resultados (agenda)

- `pnpm verify --e2e` → **9/9 PASS**; PHPUnit **100/100** (794 asserções; 15 novos em `AgendaTest`); isolamento
  **29/29**; E2E **47 PASS + 1 ignorado de propósito**; axe sem violações nas 6 telas novas.
- `pnpm perf:check` (seed) → p95 agenda dia 49,6 ms, semana 49,5 ms. `PERF_VOLUME=20000` (~10 mil agendamentos) →
  dia 105,8 ms, semana 196,9 ms (era 353 ms antes do item compacto).
- Defeito achado no ensaio local: nome de índice > 64 caracteres quebrava a migração (MySQL não desfaz DDL) →
  nomes explícitos; risco registrado na ADR-011.
- Migração nova: `2026_10_16_000001_create_schedule_tables` → publicação exige backup + `migrar`.

## Comandos executados e resultados (novo visual)

- `pnpm verify --e2e` → **9/9 PASS**; PHPUnit 100/100; E2E 47 + 1 ignorado; axe sem violações (claro/escuro).
- Defeitos achados e corrigidos: fonte com URL absoluta `/build` (quebraria em `/fauvovet`) → `base: './'`;
  rolagem lateral no celular (e-mail longo, grade de pacientes) → quebra de texto + `overflow-x: clip`.
- Web vitals: ~149 KB na 1ª visita (antes ~98; fonte em cache depois), LCP ≤ 120 ms, CLS ≤ 0,006.
  `perf:check` p95: painel 53,7 ms, agenda 54,9 ms, pacientes 38,3 ms.
- Sem migração: publicar exige só `otimizar` (recompilar telas) — guia seção 7.

## Comandos executados e resultados (ajustes + prontuário)

- `pnpm verify --e2e` → **9/9 PASS**; PHPUnit **117/117** (989 asserções); isolamento **32/32**; E2E **55 + 1
  ignorado de propósito**; axe sem violações no prontuário.
- `pnpm perf:check` p95: ficha com prontuário 63,2 ms; atendimento 55,1 ms; painel 55,1 ms.
- Migrações novas: `2026_10_16_000002_add_address_details_to_guardians`, `2026_10_16_000003_create_clinical_record_tables`.
- Pendente de conferir no servidor: acesso de saída ao ViaCEP a partir da Locaweb.

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

1. Proprietário: publicar `docs/changes/2026-10-17-prontuario.html` › seção 7 (2 migrações; backup antes) e testar o
   CEP no servidor. Sem HTTPS, só atendimentos fictícios. Design: proprietário vai retomar o assunto mais à frente.
2. Próximo incremento: **receitas e documentos em PDF** — modelos básicos (receita simples, atestado, orientações,
   termo de consentimento) com variáveis da clínica/profissional/paciente (whitelist §4.3), geração com dompdf, PDF
   emitido preservado e vinculado ao atendimento; depois anexos privados (storage fora do public_html, download
   autorizado).

## Histórico de incrementos

| Data       | Incremento                                                                                                      | HTML                                                                                         | Testes                                                             | Commit/branch                      | Sync GitHub            | Próximo passo                         |
| ---------- | --------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ | ---------------------------------- | ---------------------- | ------------------------------------- |
| 2026-10-15 | Preparação das instruções (sem implementação)                                                                   | Não aplicável                                                                                | Não executados                                                     | `0b24a5c` (main)                   | Já no remoto           | Iniciar Fase 0                        |
| 2026-10-15 | Fundação: monorepo, RLS, API multi-tenant                                                                       | [2026-10-15-fundacao-api-multitenant.html](changes/2026-10-15-fundacao-api-multitenant.html) | verify PASS (unit 17, integração 42)                               | `7d303af` (main)                   | PASS (push confirmado) | Web: login/contexto/shell             |
| 2026-10-15 | Web: login, cadastro, contexto, shell responsivo, painel, unidades, equipe, aparência                           | [2026-10-15-web-shell-login.html](changes/2026-10-15-web-shell-login.html)                   | unit 21, integração 42, E2E 22                                     | main — hash na próxima atualização | push nesta sessão      | E2E na CI; Fase 1 (equipe, pacientes) |
| 2026-10-15 | **Reescrita PHP/Laravel + MySQL** (hospedagem sem SSH)                                                          | [2026-10-15-reescrita-php-mysql.html](changes/2026-10-15-reescrita-php-mysql.html)           | verify --e2e 9/9 (PHPUnit 55, E2E 28)                              | main — hash na próxima atualização | push nesta sessão      | 1º deploy na Locaweb                  |
| 2026-10-15 | Tutorial didático de deploy na Locaweb + `pnpm deploy:copiar`                                                   | [2026-10-15-tutorial-deploy-locaweb.html](changes/2026-10-15-tutorial-deploy-locaweb.html)   | verify PASS; deploy:copiar 5 cenários                              | main — hash na próxima atualização | push nesta sessão      | Proprietário executa o deploy         |
| 2026-10-15 | **Primeira publicação na Locaweb** (pelo proprietário) + correção do domínio para `dradeboragaldino`            | [2026-10-15-tutorial-deploy-locaweb.html](changes/2026-10-15-tutorial-deploy-locaweb.html)   | smoke produção 5/5 PASS (HTTP); HTTPS inválido                     | main                               | push nesta sessão      | SSL do domínio; Fase 1 — equipe       |
| 2026-10-15 | **Fase 1 — Gestão de equipe**: convites por link, papéis, unidades, desativação                                 | [2026-10-15-equipe-convites.html](changes/2026-10-15-equipe-convites.html)                   | verify --e2e 9/9 (PHPUnit 71, isolamento 21, E2E 33)               | main — hash na próxima atualização | push nesta sessão      | Responsáveis e pacientes              |
| 2026-10-15 | **Fase 1 — Responsáveis e pacientes**: cadastro rápido, espécies amplas, vínculos N:N, busca, óbito             | [2026-10-15-pacientes-responsaveis.html](changes/2026-10-15-pacientes-responsaveis.html)     | verify --e2e 9/9 (PHPUnit 85, isolamento 25, E2E 39); perf 20k     | main — hash na próxima atualização | push nesta sessão      | Agenda                                |
| 2026-10-16 | **Fase 1 — Agenda**: dia/semana, fuso por unidade, tipos, status, conflito/encaixe, fila de espera              | [2026-10-16-agenda.html](changes/2026-10-16-agenda.html)                                     | verify --e2e 9/9 (PHPUnit 100, isolamento 29, E2E 47); perf volume | main — hash na próxima atualização | push nesta sessão      | Atendimento/prontuário                |
| 2026-10-16 | **Novo visual (design 2.0)**: fonte própria, menu escuro, degradês da clínica, painel, agenda em linha do tempo | [2026-10-16-redesign-visual.html](changes/2026-10-16-redesign-visual.html)                   | verify --e2e 9/9 (PHPUnit 100, E2E 47); axe claro/escuro           | main — hash na próxima atualização | push nesta sessão      | Prontuário                            |
| 2026-10-17 | **Fase 1 — Prontuário** + ajustes pedidos (sem "opcional", idade pela data, CEP, UF em lista)                   | [2026-10-17-prontuario.html](changes/2026-10-17-prontuario.html)                             | verify --e2e 9/9 (PHPUnit 117, isolamento 32, E2E 55)              | main — hash na próxima atualização | push nesta sessão      | Receitas/documentos PDF               |
