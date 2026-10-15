# Modelo de dados — FauvoVet (MySQL 5.7+)

> Fonte da verdade: `database/migrations/*.php`. IDs ULID (`char(26)`), `utf8mb4_unicode_ci`, InnoDB, timestamps em UTC
> (exibição no fuso da unidade). Sem recursos exclusivos do MySQL 8 (sem CHECK, CTE, colunas geradas).

## 2026_10_15_000001_create_identity_tables

| Tabela                 | Escopo      | Observações                                                                                            |
| ---------------------- | ----------- | ------------------------------------------------------------------------------------------------------ |
| `users`                | global      | e-mail único (191), senha Argon2id, `status`, contador de falhas, `locked_until`, sem "lembrar de mim" |
| `sessions`             | por usuário | sessão do Laravel no banco (payload criptografado, `SESSION_ENCRYPT=true`)                             |
| `cache`, `cache_locks` | sistema     | rate limit de login/cadastro (sem Redis na hospedagem)                                                 |
| `jobs`, `failed_jobs`  | sistema     | fila `database` (ainda sem uso)                                                                        |

## 2026_10_15_000002_create_tenancy_tables

| Tabela             | Escopo            | Integridade                                                                                                                                       |
| ------------------ | ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tenants`          | a própria clínica | PF/PJ, documento só dígitos e **não único** (ADR-008), `status` (active/suspended/closed)                                                         |
| `units`            | clínica           | `UNIQUE (tenant_id, id)`; nome único por clínica (sem diferenciar maiúsculas); fuso por unidade                                                   |
| `memberships`      | clínica           | `roles` JSON validado pela aplicação; `UNIQUE (tenant_id, user_id)`, `UNIQUE (tenant_id, id)`                                                     |
| `membership_units` | clínica           | FKs **compostas** para `memberships(tenant_id,id)` e `units(tenant_id,id)`                                                                        |
| `tenant_themes`    | clínica           | tokens `#RRGGBB` validados (AA) e modo                                                                                                            |
| `audit_events`     | clínica (ou nulo) | somente inclusão (model bloqueia UPDATE/DELETE); eventos sem clínica (ex.: falha de login) nunca aparecem para clínicas; IP só como HMAC truncado |

Tabelas de clínica registradas no firewall: `TenantQueryFirewall::TENANT_TABLES` (um teste falha se uma tabela com
`tenant_id` não estiver na lista).

## Planejado (não migrado)

Pacientes, responsáveis, agenda, prontuário etc. conforme CLAUDE.md §6.3, cada um com `tenant_id`, FKs compostas,
model `BelongsToTenant` + `TenantOwned` e testes de isolamento.
