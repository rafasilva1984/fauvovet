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

## O que vai para o servidor (gerado por `pnpm deploy:package`)

```text
.deploy/fauvovet-<versão>/
├── app-fauvovet/            → raiz do FTP, AO LADO de public_html/ (fora da web)
│   ├── app/ bootstrap/ config/ database/migrations/ lang/ resources/views/ routes/ storage/ vendor/ artisan
│   ├── .htaccess            (nega tudo, caso um dia fique em pasta pública por engano)
│   └── .env.production.example
├── public_html/fauvovet/    → dentro de public_html/
│   ├── index.php            (aponta para ../../app-fauvovet)
│   ├── .htaccess            (rewrite, nega dotfiles e qualquer outro .php)
│   └── build/ favicon.ico robots.txt
├── MANIFEST.sha256          (conferência)
└── LEIA-ME.txt
```

## Primeiro deploy (passo a passo)

**Na sua máquina (WSL), na pasta do projeto:**

1. `git pull --ff-only && pnpm verify --e2e` — tudo precisa estar `PASS`.
2. `pnpm deploy:package` — anote a pasta gerada em `.deploy/`.
3. `pnpm deploy:token` — guarde o **TOKEN** no gerenciador de senhas; copie a linha `DEPLOY_TOKEN_SHA256=...`.
4. `node scripts/php.mjs artisan key:generate --show` — chave **exclusiva** de produção (guarde no gerenciador de senhas).

**No painel da Locaweb:** 5. Selecione PHP **8.3 ou superior** para o domínio. 6. Anote host, porta, nome do banco, usuário e senha do MySQL criado.

**No FileZilla (SFTP/FTPS):** 7. Envie `app-fauvovet/` para a **raiz do FTP** (mesmo nível de `public_html/`). 8. Envie o conteúdo de `public_html/fauvovet/` para `public_html/fauvovet/` (inclusive o `.htaccess` — ative
"Mostrar arquivos ocultos" no FileZilla). 9. No servidor, copie `app-fauvovet/.env.production.example` para `app-fauvovet/.env` e edite **no servidor**:
`APP_KEY` (passo 4), `DB_*` (passo 6), `DEPLOY_TOKEN_SHA256` (passo 3), `DEPLOY_TOOLS_ENABLED=true`,
`FAUVOVET_ALLOW_SELF_SIGNUP=true` (temporário, para criar a primeira clínica) e, **se ainda não houver HTTPS**,
`APP_URL=http://...` e `SESSION_SECURE_COOKIE=false` (somente para teste, sem dados reais).

**Na sua máquina (terminal), com o token só na sessão do terminal:** 10. `export FAUVOVET_DEPLOY_TOKEN='<token do passo 3>'` 11. `pnpm deploy:remote https://dradeboragakdino.com.br/fauvovet diagnostico` — confira `php`, `extensions_missing: []`,
`database.ok: true`, `pending_migrations: 2`, avisos. (Sem HTTPS, o script exige `FAUVOVET_ALLOW_HTTP=1`.) 12. `pnpm deploy:remote https://dradeboragakdino.com.br/fauvovet migrar` → `exit_code: 0`. 13. `pnpm deploy:remote https://dradeboragakdino.com.br/fauvovet otimizar` → todos `0`. 14. Abra `https://dradeboragakdino.com.br/fauvovet/cadastro` e crie a clínica do titular. 15. No `.env` do servidor: `FAUVOVET_ALLOW_SELF_SIGNUP=false` e `DEPLOY_TOOLS_ENABLED=false`; depois apague
`app-fauvovet/bootstrap/cache/config.php` pelo FileZilla (o cache de configuração guarda os valores antigos). 16. `SMOKE_URL=https://dradeboragakdino.com.br/fauvovet pnpm smoke` — tudo `PASS`. 17. `unset FAUVOVET_DEPLOY_TOKEN`.

## Atualizações

1. `pnpm verify --e2e` → `pnpm deploy:package`.
2. **Backup** do banco pelo painel antes, se houver migração nova (o diagnóstico mostra `pending_migrations`).
3. FileZilla: reenvie `app-fauvovet/` **sem** sobrescrever `.env` e `storage/`; reenvie `public_html/fauvovet/`.
   Remova assets antigos de `build/assets/` só depois de validar.
4. No `.env` do servidor, `DEPLOY_TOOLS_ENABLED=true` e apague `bootstrap/cache/config.php`; rode `migrar` e
   `otimizar`; volte `DEPLOY_TOOLS_ENABLED=false` e apague `bootstrap/cache/config.php` de novo (a aplicação funciona
   sem o cache de configuração; ele é recriado no próximo `otimizar`).
5. `pnpm smoke`.

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
