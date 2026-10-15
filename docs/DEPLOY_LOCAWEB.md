# Deploy na Locaweb — FauvoVet

> **Estado: pronto para o primeiro deploy de TESTE; ainda não publicado.** Todo o fluxo abaixo foi ensaiado localmente
> numa simulação fiel (pacote real, `APP_ENV=production`, migração e caches via HTTP — ver guia HTML da mudança), mas
> **não** no servidor da Locaweb. Nenhum dado real antes de HTTPS ativo (ver `docs/SECURITY.md`).

## Ambiente informado/pendente

| Item                              | Valor                                                                                                                                |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Plano                             | **Hospedagem Dedicada 4 GB**, só painel Locaweb (informado em 15/10/2026)                                                            |
| SSH / Composer / Node no servidor | **Não** (informado). Build e `vendor/` são feitos localmente                                                                         |
| Banco                             | **MySQL do painel** (instalação feita pelo proprietário em 15/10/2026). Versão/host: **pendente** — veja no painel ou no diagnóstico |
| Domínio                           | `dradeboragakdino.com.br` já apontado para a hospedagem (informado). Aplicação em `/fauvovet`                                        |
| HTTPS do domínio                  | **pendente de verificação** (bloqueante para dados reais)                                                                            |
| Versão do PHP                     | **pendente** — o pacote exige **PHP ≥ 8.3** (testado em 8.3 na CI e 8.5 localmente). Selecionar 8.3+ no painel                       |
| Protocolo do FileZilla            | usar **SFTP** se o painel oferecer; senão **FTPS** (FTP explícito sobre TLS). Nunca FTP puro                                         |
| Cron                              | não necessário nesta versão                                                                                                          |

> **Tutorial completo, para quem nunca fez deploy:**
> [`docs/changes/2026-10-15-tutorial-deploy-locaweb.html`](changes/2026-10-15-tutorial-deploy-locaweb.html). Abra-o no
> navegador (no Windows: `\\wsl.localhost\Ubuntu\home\rafa_silva\code\fauvovet\docs\changes\`). Este arquivo
> é só o resumo de referência.

## O que vai para onde

```text
Pacote (C:\fauvovet-deploy\ após pnpm deploy:copiar)      Servidor (lado direito do FileZilla)
app-fauvovet\                                     →     /app-fauvovet/            (raiz do FTP, ao lado de public_html; PRIVADO)
public_html\fauvovet\  (index.php, .htaccess, build) →  /public_html/fauvovet/    (PÚBLICO)
LEIA-ME.txt, MANIFEST.sha256                      →     não enviar
```

O `.env` fica em `/app-fauvovet/.env`. Ele é criado na primeira instalação por `pnpm deploy:copiar --primeira-instalacao`
e preenchido no Bloco de Notas: `APP_KEY`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`DEPLOY_TOKEN_SHA256` e, durante a instalação, `DEPLOY_TOOLS_ENABLED=true` + `FAUVOVET_ALLOW_SELF_SIGNUP=true`.

## Resumo do primeiro deploy (detalhes no tutorial)

| Etapa | Onde                | O quê                                                                                                                             |
| ----- | ------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| A     | Terminal Ubuntu     | `git pull --ff-only` → `pnpm db:start` → `pnpm verify --e2e` → `pnpm deploy:package` → `pnpm deploy:copiar --primeira-instalacao` |
| B     | Painel Locaweb      | PHP ≥ 8.3 (Resumo de Uso › Versão do PHP); anotar dados do MySQL (Banco de dados) e do FTP (Arquivos e FTP); conferir HTTPS       |
| C     | Terminal Ubuntu     | `pnpm deploy:token` (TOKEN + `DEPLOY_TOKEN_SHA256`) e `node scripts/php.mjs artisan key:generate --show` (APP_KEY)                |
| D     | Bloco de Notas      | preencher `C:\fauvovet-deploy\app-fauvovet\.env`                                                                                  |
| E     | FileZilla           | SFTP (porta 22) ou FTPS explícito (porta 21); mostrar ocultos; enviar as duas pastas                                              |
| F     | Terminal Ubuntu     | ` export FAUVOVET_DEPLOY_TOKEN='…'` → `pnpm deploy:remote <URL> diagnostico` → `migrar` → `otimizar`                              |
| G     | Navegador/FileZilla | criar a clínica em `/fauvovet/cadastro`; `.env` com os dois flags em `false`; apagar `bootstrap/cache/config.php`                 |
| H     | Terminal/Windows    | `SMOKE_URL=<URL> pnpm smoke` (5 PASS) → `unset FAUVOVET_DEPLOY_TOKEN` → apagar `C:\fauvovet-deploy`                               |

## Atualizações

Tutorial, seção 7. Em resumo: backup do banco → flag de deploy ligado (com token novo) → reenviar `app-fauvovet` **sem**
`storage/` e sem `.env` → reenviar `public_html/fauvovet` → `migrar` + `otimizar` → flag desligado → apagar
`bootstrap/cache/config.php` → `pnpm smoke`.

> Por que apagar `bootstrap/cache/config.php`? Depois do `otimizar`, o Laravel lê a configuração do cache e ignora
> edições no `.env` até o cache ser apagado. Sem SSH, isso é feito pelo FileZilla.

## Rollback

- **Código:** reenvie o pacote da versão anterior (guarde a pasta `.deploy/` de cada versão publicada).
- **Banco:** migrações são _forward-only_; reverter exige restaurar o backup feito antes do deploy.
- **Emergência:** renomeie `public_html/fauvovet/index.php` pelo FileZilla para tirar o sistema do ar.

## Troubleshooting

| Sintoma                                  | Causa provável                                                                                              |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| 500 em todas as páginas logo após enviar | `.env` ausente/incompleto ou `APP_KEY` vazio; veja `app-fauvovet/storage/logs/laravel.log` pelo FileZilla   |
| 500 só antes de migrar                   | normal: tabelas `sessions`/`cache` ainda não existem; rode `migrar`                                         |
| `diagnostico` responde 404               | `DEPLOY_TOOLS_ENABLED=false`, token errado, ou cache de config antigo (apague `bootstrap/cache/config.php`) |
| 419 ao enviar formulários                | `SESSION_SECURE_COOKIE=true` sem HTTPS, ou `SESSION_PATH`/`APP_URL` sem `/fauvovet`                         |
| CSS/JS não carregam                      | `public_html/fauvovet/build/` não enviado ou `APP_URL` incorreto                                            |
| `extensions_missing` não vazio           | habilite a extensão no painel (ex.: `pdo_mysql`, `sodium`) ou troque a versão do PHP                        |
| Erro de conexão com o banco              | host do painel (normalmente externo, `*.mysql.dbaas.com.br`), porta e usuário conforme o painel             |
