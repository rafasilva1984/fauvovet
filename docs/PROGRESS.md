# FauvoVet — Checkpoint e ponto de retomada

> **Status inicial:** Apenas especificação e documentação inicial preparadas. **Nenhuma funcionalidade, teste, integração, deploy, commit ou push foi realizado por este pacote.**
> **Repositório esperado:** https://github.com/rafasilva1984/fauvovet
> **Atualizado em:** 2026-10-15 (America/Sao_Paulo)
> **Regra:** Atualize este documento a cada incremento, bloqueio e antes de encerrar qualquer sessão do Claude Code.

## Situação atual

- Fase planejada: 0 — Descoberta, fundação e ambiente de desenvolvimento.
- Implementação funcional verificada: nenhuma.
- Ambiente local identificado: não verificado neste pacote.
- Branch, commit e sincronização remota: não verificados neste pacote.
- Última documentação HTML de alteração: nenhuma, pois não foi implementada uma mudança funcional.

## O que já está especificado

- SaaS veterinário multi-tenant com busca clínica transversal e templates por clínica.
- Git automático por incremento, documentação HTML, QA local, suporte WSL/Git Bash e deployment na Locaweb conforme o plano suportar.
- Todas as demais regras em `CLAUDE.md`.

## Testes efetivamente executados

- Nenhum teste de aplicação executado: não existe aplicação neste pacote.

## Riscos/bloqueios em aberto

- É necessário inspecionar os arquivos/branches e permissões reais do repositório.
- É necessário confirmar características do plano Locaweb antes de tentar deploy.
- O escopo MVP deve ser entregue incrementalmente conforme o roadmap.

## Próxima ação exata (sessão Claude Code)

1. Abrir a raiz do repositório FauvoVet, ler integralmente `CLAUDE.md` e este checkpoint.
2. Inspecionar `git status --short --branch` e `git remote -v`, seguido de `git fetch origin` quando acessível.
3. Diagnosticar terminal WSL/Git Bash e runtimes locais, instalar o que faltar.
4. Preservar quaisquer alterações existentes no repositório; não sobrescrever dados ou commits.
5. Iniciar primeira vertical slice da Fase 0 com testes locais, e preencher um **novo HTML** a partir de `docs/changes/TEMPLATE.html`.
6. Atualizar este arquivo, executar testes, commitar e publicar incremento após validação, verificando sincronização com `origin`.

## Histórico de incrementos

| Data | Incremento | HTML | Testes | Commit/branch | Sync GitHub | Próximo passo |
|---|---|---|---|---|---|---|
| 2026-10-15 | Preparação das instruções (sem implementação) | Não aplicável | Não executados | Não verificado | Não realizado | Inspecionar repositório e iniciar Fase 0 |
