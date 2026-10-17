# Roadmap — FauvoVet

Ordem conforme CLAUDE.md §11. Atualizado em 2026-10-15 (stack PHP/MySQL — ADR-010).

## Fase 0 — Fundação (em andamento)

- [x] Stack compatível com a hospedagem (Laravel 13, PHP ≥ 8.3, MySQL 5.7+, sem SSH) e CI com PHP 8.3.
- [x] MySQL local reproduzível sem root (Percona 5.7) + migrações.
- [x] Login, sessão segura, escolha de clínica/unidade, RBAC, cadastro de clínica, unidades, equipe (leitura), aparência, auditoria.
- [x] Isolamento em 5 camadas + suíte com duas clínicas.
- [x] Interface responsiva (Blade + Alpine CSP + Tailwind), E2E desktop/celular, axe AA.
- [x] Kit de deploy sem SSH (pacote, token, migração/diagnóstico via HTTP, smoke) ensaiado localmente.
- [x] Primeiro deploy de teste na Locaweb (15/10/2026, `dradeboragaldino.com.br/fauvovet`, smoke 5/5).
- [ ] HTTPS ativo + backup/restauração testados (bloqueante para dados reais).

## Fase 1 — MVP clínico operável

- [x] Gestão de equipe: convite por link de uso único, papéis, unidades por membro, desativação (15/10/2026).
- [ ] Recuperação de senha, MFA do titular (dependem de e-mail transacional/TOTP).
- [x] Responsáveis e pacientes: cadastro rápido, 16 espécies + exóticos, vínculos N:N, busca, óbito, aviso de duplicado (15/10/2026).
- [x] Agenda: dia/semana por unidade e profissional, fuso da unidade, tipos, status, conflito, encaixe, fila de espera (16/10/2026).
- [x] Atendimento/prontuário: queixa, anamnese, sinais vitais, exame físico, hipóteses × diagnósticos, conduta; finalizar e assinar bloqueia; adendos; alertas; peso (17/10/2026).
- [ ] Receitas e documentos em PDF (modelos básicos + dompdf), anexos privados ← próximo

Depois: modelos de documento por clínica, vacinação.

## Fase 2 — Busca clínica e importação documental

Busca com FULLTEXT do InnoDB e índice próprio por clínica (avaliar qualidade em português), facetas e trechos com
origem; extração de PDF/DOCX em PHP (fila `database` + cron); templates por clínica com versões. Aceite: CLAUDE.md §3.3.

## Fases 3–6

Ver CLAUDE.md §11.
