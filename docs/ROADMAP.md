# Roadmap — FauvoVet

Ordem conforme CLAUDE.md §11. Atualizado em 2026-10-15.

## Fase 0 — Fundação (em andamento)

- [x] Monorepo pnpm, TypeScript estrito, ESLint, Prettier, `.gitattributes`, `.env.example`, MIT, CI.
- [x] PostgreSQL local reproduzível sem root (embedded) + migrações versionadas com checksum.
- [x] API: login/logout, sessão segura, CSRF, seleção de tenant/unidade, RBAC inicial, onboarding, unidades, tema, auditoria, health.
- [x] RLS + suíte de isolamento com dois tenants (HTTP e banco).
- [x] Scripts: `bootstrap`, `dev`, `db:*`, `verify`, `docs:validate`, `security:secrets`, `perf:check`, `smoke`.
- [x] Web: design system inicial (tokens + tema por tenant), login, cadastro, seleção de contexto, shell responsivo, painel por função, unidades, equipe, aparência.
- [x] E2E Playwright desktop + mobile, axe WCAG AA (claro/escuro), Web Vitals de laboratório.
- [ ] Docker Compose (dev e VPS) testado em ambiente com Docker.
- [ ] Política mínima de backup documentada e testada (restore).

## Fase 1 — MVP clínico operável

Gestão de usuários (convite, recuperação, MFA), responsáveis e pacientes, agenda, atendimento/prontuário com bloqueio e
adendos, anexos privados, modelos básicos + PDF, UI premium mobile.

## Fase 2 — Busca clínica e importação documental

`ClinicalSearchService` sobre PostgreSQL FTS (pt-BR + `unaccent` + `pg_trgm`), facetas, trechos, origem; worker com fila
para extração PDF/DOCX/DOC; templates por tenant com versões. Aceite: casos 1–10 de CLAUDE.md §3.3.

## Fases 3–6

Consolidação clínica (vacinas, exames, internação, cirurgia, portal), gestão comercial, integrações e SaaS comercial,
inteligência assistida — ver CLAUDE.md §11.
