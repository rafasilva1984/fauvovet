# Ideias e melhorias propostas — FauvoVet

| Ideia                                                          | Impacto | Urgência                        | Custo | Dependências            | Justificativa                                                         |
| -------------------------------------------------------------- | ------- | ------------------------------- | ----- | ----------------------- | --------------------------------------------------------------------- |
| Rate limit em Redis                                            | Alto    | Antes de >1 réplica             | Baixo | Redis                   | Limitador atual é por processo                                        |
| Verificação de e-mail no onboarding                            | Alto    | Antes de abrir cadastro público | Médio | Provedor de e-mail      | Evita contas com e-mail de terceiros e reduz enumeração               |
| Avaliar Drizzle como query builder tipado                      | Médio   | Fase 1                          | Médio | ADR-002                 | Tipagem de linhas sem perder SQL/RLS explícitos                       |
| Atualizar `last_seen_at` da sessão com _throttle_              | Baixo   | Fase 1                          | Baixo | —                       | Gestão de dispositivos e expiração por inatividade                    |
| gitleaks + `pnpm audit` na CI                                  | Médio   | Fase 0/1                        | Baixo | —                       | Complementa varredura local simples                                   |
| Docker Compose testado (dev + VPS)                             | Alto    | Fase 0                          | Médio | Ambiente com Docker     | Requisito de deploy Locaweb VPS                                       |
| Teste automatizado de contraste do tema padrão em claro/escuro | Médio   | Incremento web                  | Baixo | —                       | Garantir WCAG AA do tema FauvoVet                                     |
| CSP com nonce (via `proxy.ts`)                                 | Médio   | Antes da produção               | Médio | Renderização dinâmica   | Remover `'unsafe-inline'` de `script-src`                             |
| Fontes self-hosted com licença OFL                             | Baixo   | Fase 1                          | Baixo | Avaliar licença         | Identidade tipográfica sem depender de fontes do sistema              |
| Busca global no topo (atalho `/`)                              | Alto    | Fase 2                          | Alto  | `ClinicalSearchService` | Espaço reservado no cabeçalho; não exibir controle antes de funcionar |
| Encerrar processos filhos do `pnpm dev` ao receber SIGTERM     | Baixo   | Fase 1                          | Baixo | —                       | `timeout`/kill do pai deixa `tsc --watch` órfão (Ctrl+C funciona)     |
