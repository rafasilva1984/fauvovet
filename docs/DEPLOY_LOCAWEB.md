# Deploy na Locaweb — FauvoVet

> **Estado: NÃO PUBLICÁVEL ainda.** O plano Locaweb contratado **não foi verificado**. Nada aqui atesta que DNS,
> TLS, hospedagem ou sistema estejam configurados em `https://dradeboragakdino.com.br/fauvovet`.

## Checklist de verificação do plano (preencher antes de qualquer deploy)

| Item                                                          | Valor verificado |
| ------------------------------------------------------------- | ---------------- |
| Tipo de plano (compartilhado / VPS / cloud)                   | não verificado   |
| Acesso root/SSH                                               | não verificado   |
| Node.js persistente, Docker, proxy reverso                    | não verificado   |
| CPU / RAM / disco / processos                                 | não verificado   |
| PostgreSQL disponível e política de acesso                    | não verificado   |
| DNS e TLS do domínio; possibilidade de encaminhar `/fauvovet` | não verificado   |
| E-mail transacional; armazenamento privado de objetos         | não verificado   |
| Protocolo de transferência (SFTP/FTPS) para FileZilla         | não verificado   |

## Decisão pendente: banco de dados (MySQL do painel × PostgreSQL)

O FauvoVet **não roda em MySQL** sem reescrever a camada de isolamento:

- O isolamento entre clínicas é garantido no banco por **Row-Level Security** (ADR-003), que o MySQL não tem; em MySQL
  ele dependeria só do código da aplicação (um filtro esquecido = vazamento entre clínicas).
- A busca clínica (Fase 2) usa `tsvector` em português, `unaccent` e `pg_trgm`, recursos do PostgreSQL.
- Também usamos funções `SECURITY DEFINER`, FKs compostas, `jsonb`, `text[]` e trigger de auditoria append-only.

**Recomendação:** instalar PostgreSQL 18 no próprio servidor (pacote do sistema ou Docker), escutando só em
`127.0.0.1`, se houver root/SSH. 4 GB comportam a fase inicial (medido localmente: API ~200 MB de RSS; PostgreSQL e
Next.js a dimensionar no servidor). Sem root: PostgreSQL gerenciado em região Brasil (avaliar LGPD/latência) ou trocar
para VPS. O MySQL do painel pode existir, mas não será usado pelo FauvoVet.

## Requisitos do que já existe

- API Node.js 24 **persistente** (`apps/api`, `node dist/main.js`) + PostgreSQL 18 com dois papéis
  (migração e aplicação sem BYPASSRLS). **Não roda em hospedagem compartilhada só-PHP/HTML.**
- Proxy reverso: `/fauvovet/api/` → API (sem reescrever o caminho; a API já usa o prefixo `${BASE_PATH}/api/v1`).
- Variáveis: `NODE_ENV=production`, `COOKIE_SECURE=true`, `TRUST_PROXY=true`, `PUBLIC_ORIGIN=https://<domínio>`,
  `DATABASE_URL`/`MIGRATION_DATABASE_URL` reais **somente no servidor** (nunca no Git ou em `public_html`).
- Migração: `pnpm db:migrate` com backup prévio. Seeds **nunca** em produção (o CLI recusa).

## FileZilla

FileZilla é apenas cliente de transferência. Usar **SFTP ou FTPS**. Nunca enviar `.env`, dumps, uploads ou o backend
como pasta pública. O procedimento será detalhado após verificar o plano.

## Rollback (modelo)

Manter a versão anterior do artefato; migrações são _forward-only_ — reverter exige migração corretiva e backup
restaurável testado.
