# FauvoVet — Estratégia e evidências de testes

> **Estado:** documento inicial; o produto e seus scripts de teste ainda não foram implementados neste pacote. Não declarar comandos como disponíveis antes de confirmá-los no repositório.

## Ambientes

- Desenvolvimento preferencial: WSL2/Ubuntu, Node.js + pnpm + Docker Desktop integrado quando requerido.
- Terminal alternativo: Git Bash no Windows; documentar dependências e diferenças verificadas.
- Testes: banco e volumes distintos de produção, fixtures inteiramente sintéticas e pelo menos dois tenants (A e B).

## Gates desejados (implementar gradualmente)

1. Lint/format/types e build.
2. Unitários e integração de API/banco.
3. Isolamento cross-tenant inclusive pesquisa e documentos.
4. E2E: agendamento, pacientes, prontuário, templates, busca e mobile.
5. Performance: baseline documentado, consultas e latência p50/p95/p99, frontend Core Web Vitals.
6. Segurança: vazamento de segredo, autenticação, autorização, uploads, dependências.
7. Verificação de documentação HTML e `docs/PROGRESS.md`.

## Evidências por incremento

| Data | Escopo | Comando real | Ambiente | Resultado (PASS/FAIL/NOT RUN) | Evidência/causa |
|---|---|---|---|---|---|
| 2026-10-15 | Pacote inicial de documentação | Nenhum teste de aplicação | Não se aplica | NOT RUN | A aplicação ainda não existe neste pacote |

## Antes do deploy

Somente publicar após testes locais efetivos, revisão de segredos, build verificado, documentação da mudança, plano de backup/rollback e smoke do artefato. FileZilla apenas transfere artefatos compatíveis com a hospedagem.
