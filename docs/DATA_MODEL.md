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

## 2026_10_16_000001_create_schedule_tables

| Tabela              | Escopo  | Integridade                                                                                                                                                                                                                                                                                                      |
| ------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `appointment_types` | clínica | nome único por clínica, duração 5–480 min, cor `#RRGGBB` com contraste ≥ 3:1 sobre branco, `active` (desativar em vez de apagar); 6 tipos padrão criados no primeiro uso da agenda                                                                                                                               |
| `appointments`      | clínica | `starts_at`/`ends_at` **UTC** (DATETIME), fuso da unidade na tela; FKs **compostas** para unidade, paciente, tipo e profissional (`memberships`); 7 status + carimbos de tempo; `is_walk_in`; índices `(tenant_id, unit_id/professional/patient, starts_at)` com nomes curtos (limite de 64 caracteres do MySQL) |

## 2026_10_16_000002_add_address_details_to_guardians

`guardians` ganha `address_number`, `address_complement` e `district` (endereço em partes; rua/bairro/cidade/UF vêm do
CEP pela consulta `/cep/{cep}`). Migração aditiva.

## 2026_10_16_000003_create_clinical_record_tables

| Tabela                | Escopo  | Integridade                                                                                                                                                                                                 |
| --------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `encounters`          | clínica | rascunho/finalizado; textos clínicos + sinais vitais em colunas; FKs compostas (unidade, paciente, agendamento, tipo, assinante); `UNIQUE (tenant_id, appointment_id)`; **imutável após finalizar** (model) |
| `encounter_diagnoses` | clínica | suspeito/confirmado/descartado + código opcional; `patient_id` repetido para busca futura; imutável quando o atendimento está finalizado                                                                    |
| `encounter_addenda`   | clínica | somente inclusão (model bloqueia update/delete); autor e vínculo do autor                                                                                                                                   |
| `patient_weights`     | clínica | histórico de peso; 1 por atendimento (`UNIQUE (tenant_id, encounter_id)`)                                                                                                                                   |
| `patient_alerts`      | clínica | alergia/condição crônica/medicação/comportamento/outro, gravidade; resolvido em vez de apagado                                                                                                              |

## 2026_10_17_000001_create_document_tables

| Tabela               | Escopo  | Integridade                                                                                                                                                                                      |
| -------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `document_templates` | clínica | nome único por clínica; texto com variáveis só da whitelist (`App\Documents\Placeholders`); `version` incrementa ao editar texto/título/categoria; 5 modelos padrão criados no primeiro uso      |
| `issued_documents`   | clínica | texto final + caminho do PDF (disco privado `storage/app/private/documents/{clínica}/{ano}/{id}.pdf`), SHA-256, bytes, código de verificação único, emissor e vínculo; **imutável**, só anulação |

## 2026_10_18_000001_create_attachments_table

| Tabela        | Escopo  | Integridade                                                                                                                                                                                                                                         |
| ------------- | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `attachments` | clínica | FKs compostas (paciente, atendimento); mime detectado pelo conteúdo, extensão gravada, tamanho, SHA-256; arquivo em `storage/app/private/attachments/{clínica}/{ano}/{id}.{ext}` (nome aleatório); **imutável**, só "removido" (oculto, com motivo) |

## 2026_10_18_000002_create_vaccination_tables

| Tabela         | Escopo  | Integridade                                                                                                                                                                                                 |
| -------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `vaccines`     | clínica | catálogo/protocolo: espécies (JSON de códigos; vazio = todas), doses da série, intervalo, reforço; nome único por clínica; 7 modelos genéricos criados no 1º uso                                            |
| `vaccinations` | clínica | FKs compostas (paciente, vacina, atendimento, aplicador); dose, data, lote, fabricante, validade, local, próxima dose; **imutável**, só anulação com motivo; índices por paciente+vacina e por próxima dose |

## 2026_10_18_000003_create_clinical_case_tables

| Tabela                   | Escopo  | Integridade                                                                                                                                                                                                                                          |
| ------------------------ | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `clinical_cases`         | clínica | série (`criteria` JSON = filtros da busca) ou relato; título, pergunta clínica, resumo, área, situação aberto/encerrado; contador de pseudônimos                                                                                                     |
| `clinical_case_patients` | clínica | FKs compostas (caso, paciente, atendimento de referência); pseudônimo "Caso NN", desfecho, observações da equipe, consentimento do tutor (pendente/autorizado/recusado + data + quem registrou); um paciente por caso; remover não toca o prontuário |

## 2026_10_19_000001_create_clinical_search_tables

| Tabela                      | Escopo  | Integridade                                                                                                                                                                        |
| --------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `clinical_search_documents` | clínica | trecho pesquisável derivado do prontuário (campo do atendimento finalizado, diagnóstico, adendo, alerta, documento, vacina); texto original + normalizado; FK composta ao paciente |
| `clinical_search_terms`     | clínica | termos distintos normalizados por trecho; PK `(tenant_id, term, document_id)` para busca por prefixo; cascata ao apagar o trecho                                                   |
| `clinical_search_status`    | clínica | versão, número de trechos e data da última (re)construção do índice da clínica                                                                                                     |

Tabelas de clínica registradas no firewall: `TenantQueryFirewall::TENANT_TABLES` (um teste falha se uma tabela com
`tenant_id` não estiver na lista).

## Planejado (não migrado)

Vacinas, exames estruturados, internação etc. conforme CLAUDE.md §6.3, cada um com `tenant_id`, FKs compostas,
model `BelongsToTenant` + `TenantOwned` e testes de isolamento.
