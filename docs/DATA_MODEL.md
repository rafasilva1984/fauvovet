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

## 2026_10_15_000003_create_invitations_table

| Tabela        | Escopo  | Integridade                                                                                                                                                                               |
| ------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `invitations` | clínica | `token_hash` = SHA-256 do token (único; o token em claro nunca é salvo); `expires_at` (7 dias); `accepted_*`/`revoked_*` com autor; `UNIQUE (tenant_id, id)`; índice `(tenant_id, email)` |

## 2026_10_15_000004_create_guardians_and_patients_tables

| Tabela             | Escopo  | Integridade                                                                                                                                                                                                                 |
| ------------------ | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `guardians`        | clínica | documento e telefones só com dígitos; CPF/CNPJ **não único** (aviso de duplicado só dentro da clínica); `status` active/archived; índices `(tenant_id, status, name)`, `(tenant_id, phone)`, `(tenant_id, document_number)` |
| `patients`         | clínica | `species` = código de `App\Support\Species` + `species_detail` livre; nascimento real ou estimado (`birth_date_estimated`); `status` active/deceased/archived + `deceased_on`; sem dados clínicos                           |
| `guardian_patient` | clínica | PK `(patient_id, guardian_id)`; FKs **compostas** para `guardians(tenant_id,id)` e `patients(tenant_id,id)`; `relationship` primary/co_guardian/authorized                                                                  |

Tabelas de clínica registradas no firewall: `TenantQueryFirewall::TENANT_TABLES` (um teste falha se uma tabela com
`tenant_id` não estiver na lista).

## Planejado (não migrado)

Agenda, prontuário (com alergias, peso histórico, vacinas) etc. conforme CLAUDE.md §6.3, cada um com `tenant_id`, FKs compostas,
model `BelongsToTenant` + `TenantOwned` e testes de isolamento.
