# Modelo de dados — FauvoVet

> Fonte da verdade: `apps/api/migrations/*.sql`. Este documento resume o que **está migrado**.

## 0001_foundation (aplicada)

| Tabela             | Escopo           | RLS                                             | Observações                                                                                             |
| ------------------ | ---------------- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `tenants`          | tenant (id)      | `id = current_tenant`                           | PF/PJ, documento só dígitos (não único — ADR-008), status                                               |
| `units`            | tenant           | `tenant_id = current_tenant`                    | `UNIQUE (tenant_id, id)` para FKs compostas; nome único por tenant (case-insensitive); fuso por unidade |
| `users`            | global           | si mesmo + membros do tenant ativo              | e-mail minúsculo único; Argon2id; contador de falhas e bloqueio                                         |
| `memberships`      | tenant           | `tenant_id = current_tenant`                    | `roles text[]` com CHECK na lista de papéis; `UNIQUE (tenant_id, user_id)`                              |
| `membership_units` | tenant           | `tenant_id = current_tenant`                    | restrição opcional de unidades; FKs compostas impedem cruzar tenants                                    |
| `user_sessions`    | usuário          | `user_id = current_user`                        | só hash SHA-256 do token; contexto ativo com FKs compostas para vínculo/unidade                         |
| `tenant_themes`    | tenant           | `tenant_id = current_tenant`                    | tokens de cor `#RRGGBB` (CHECK) e modo; sem CSS por cliente                                             |
| `audit_events`     | tenant (ou nulo) | lê só do tenant ativo; insere nulo ou do tenant | **append-only** (trigger bloqueia UPDATE/DELETE inclusive do dono)                                      |

Funções (`schema app`): `current_tenant_id()`, `current_user_id()`, `auth_find_user(email)`,
`auth_register_login_result(...)`, `auth_resolve_session(hash)`, `my_memberships()` (todas `SECURITY DEFINER` com
`search_path` fixo, exceto as duas primeiras).

Convenções: IDs `uuid` gerados no servidor (`gen_random_uuid()`), `timestamptz`, `updated_at` por trigger, auditoria
sem dados sensíveis (e-mails de falha de login são registrados apenas como impressão digital SHA-256 truncada).

## Planejado (não migrado)

`TenantPlan`, `Subscription`, `Guardian`, `Patient`, `GuardianPatientLink`, `Appointment`, `Encounter`, `ClinicalNote`,
`Diagnosis` … conforme CLAUDE.md §6.3, introduzidos por vertical slice com migração própria.
