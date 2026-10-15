# Ideias e melhorias propostas — FauvoVet

> Revisado em 2026-10-15 após a troca de stack (ADR-010); ideias da stack Node foram removidas.

| Ideia                                                                                           | Impacto | Urgência                        | Custo | Dependências       | Justificativa                                                                    |
| ----------------------------------------------------------------------------------------------- | ------- | ------------------------------- | ----- | ------------------ | -------------------------------------------------------------------------------- |
| Verificação de e-mail no onboarding                                                             | Alto    | Antes de abrir cadastro público | Médio | Provedor de e-mail | Evita contas com e-mail de terceiros e reduz enumeração                          |
| Busca clínica em MySQL: FULLTEXT InnoDB × tabela de termos normalizados (sem acento, sinônimos) | Alto    | Fase 2                          | Médio | ADR-010            | MySQL não tem `tsvector`/`unaccent`; medir relevância em pt-BR antes de escolher |
| Criação da primeira clínica sem ligar o cadastro público (endpoint de deploy protegido)         | Médio   | Antes da produção               | Baixo | —                  | Evita janela com cadastro aberto                                                 |
| Script de envio por FTPS automatizado (como no outro projeto)                                   | Médio   | Após 1º deploy manual           | Médio | Dados do FTP       | Menos erro manual; manter FileZilla como alternativa                             |
| Backup lógico agendado (dump via cron do painel) + teste de restauração                         | Alto    | Antes de dados reais            | Médio | Cron do painel     | A hospedagem pode não ter backup automático                                      |
| gitleaks na CI (além da varredura local)                                                        | Médio   | Fase 1                          | Baixo | —                  | Detecção de segredos mais completa                                               |
| Busca global no topo (atalho `/`)                                                               | Alto    | Fase 2                          | Alto  | Busca clínica      | Reservar espaço; não exibir controle antes de funcionar                          |
