# CLAUDE.md — FauvoVet

> **Projeto:** FauvoVet — Plataforma SaaS multi-tenant para medicina veterinária e gestão de negócios pet  
> **Versão da especificação:** 1.1 — 15/10/2026  
> **Idioma do produto:** Português do Brasil (`pt-BR`)  
> **Moeda e fuso inicial:** BRL; fuso horário configurável por unidade, padrão `America/Sao_Paulo`  
> **Endereço inicial pretendido:** `https://dradeboragakdino.com.br/fauvovet`  
> **Hospedagem inicialmente prevista:** Locaweb, sujeita à validação do plano contratado  
> **Repositório oficial (público):** https://github.com/rafasilva1984/fauvovet  
> **Ambientes de desenvolvimento:** Windows com WSL2/Ubuntu ou Git Bash; portabilidade para Linux/macOS  
> **Publicação:** inicialmente em `/fauvovet`, com FileZilla para transferência de arquivos quando aplicável  
> **Estado:** Projeto a construir. Nada neste documento equivale a funcionalidade já implementada.

---

## 1. Missão e papel do Claude Code

Você é responsável por atuar como **arquiteto de software, product engineer, desenvolvedor full-stack, especialista em segurança, designer de produto, QA e DevOps** na criação evolutiva do **FauvoVet**.

O objetivo é construir um **SaaS comercial real, seguro, moderno, elegante e preparado para expansão**, e não apenas demonstrar telas. O sistema atenderá:

1. Médico-veterinário autônomo, inclusive atendimento domiciliar e em diferentes espécies.
2. Consultório ou clínica veterinária com recepção, um ou vários veterinários.
3. Hospital veterinário com internação, equipe, plantões, procedimentos e estoque.
4. Operações opcionais de pet shop, banho e tosa e venda de produtos/serviços.
5. No futuro, redes de clínicas com múltiplas unidades e governança centralizada.

**Princípios inegociáveis:**

- Isolamento rígido entre tenants (cada clínica/profissional constitui um ambiente independente).
- Prontuário veterinário como registro clínico confiável, rastreável e protegido.
- **Busca clínica transversal e inteligente** como principal diferencial competitivo.
- Identidade visual, documentos e fluxos personalizáveis por tenant.
- Interface premium, responsiva, acessível, rápida e simples para quem trabalha com o paciente na frente.
- Arquitetura modular e sustentável, que permita alterações futuras sem reescritas extensas.
- Desenvolvimento incremental com testes, migrações, documentação e funcionalidades demonstráveis.
- **Git automatizado**: `fetch`/`pull`, `commit` e `push` sem solicitações repetidas de autorização, após cada incremento validado; jamais descartar trabalho ou sobrescrever histórico.
- **Continuidade obrigatória**: atualizar `docs/PROGRESS.md` ao longo do desenvolvimento e em cada ponto de parada.
- **Documentação HTML por implementação**: criar um guia HTML reproduzível com instalação, aplicação da mudança, testes e rollback para **cada incremento efetivamente implementado**, desde a primeira mudança.
- Performance, baixo consumo de recursos, otimização de consultas, fluidez no mobile e usabilidade como critérios de engenharia desde o primeiro commit.

Não copiar identidade visual, código-fonte, marcas, ícones proprietários, textos comerciais ou interfaces do SimplesVet. Usar o concorrente **exclusivamente como referência de escopo funcional**, criando UX, arquitetura e implementação originais.

### 1.1 Como trabalhar

- Antes de codificar, inspecione a estrutura existente do repositório e documente o que já existe.
- Se estiver vazio, inicialize a arquitetura proposta, sem criar dezenas de módulos fictícios.
- Priorize uma **vertical slice funcional** por vez, contendo UI, API, domínio, persistência, permissões e testes.
- Para cada entrega, informe: o que funciona, o que foi testado, pendências, riscos e a próxima prioridade.
- Este projeto está autorizado a executar instalações e configurações **no ambiente local de desenvolvimento** necessárias para desenvolver e testar, sem pedir confirmação a cada pacote. Preferir processos reproduzíveis, documentados e reversíveis. O consentimento não se estende a pagamentos, exclusão de dados, acesso indevido a terceiros ou alterações destrutivas em produção.
- Ao final de cada incremento: testar localmente, gerar documentação HTML, atualizar checkpoint, versionar e sincronizar com o GitHub; ver se o workspace e o remote permanecem alinhados.
- Jamais simular uma integração real sem rotular explicitamente como *mock* ou ambiente de desenvolvimento.
- Não afirmar que algo está pronto só porque há um botão ou uma tela estática.
- Sempre que identificar melhoria de segurança, produto, experiência ou performance, registre proposta e justificativa em `docs/DECISIONS.md` ou `docs/IDEAS.md`, conforme o caso.
- Quando houver ambiguidade, escolha a alternativa mais segura, reversível e compatível com a arquitetura; registre a decisão. Pergunte apenas sobre decisões irreversíveis, comerciais, jurídicas ou que dependam de credenciais não fornecidas.
- Nunca realizar ações destrutivas em produção, migrações irreversíveis, cobrança real ou envio de mensagens reais sem autorização explícita.

---

## 2. Escopo de referência: SimplesVet

**Referências oficiais verificadas em 14/10/2026:**

- Planos e módulos: https://simples.vet/precos/
- Visão funcional: https://simples.vet/funcionalidades/
- Comparativo detalhado de menus e recursos: https://suporte.simples.vet/pt-BR/articles/9911760-comparativo-de-planos-do-simplesvet
- Agenda: https://simples.vet/funcionalidades/agenda/
- Internação: https://simples.vet/funcionalidades/internacao/

**Meta de produto:** alcançar cobertura funcional equivalente às categorias relevantes dos planos clínicos/hospitalares do SimplesVet, incluindo seus módulos opcionais e funções pet quando aplicáveis, e ultrapassar essa referência em busca, organização de dados clínicos, personalização e experiência de uso.

**Regra de honestidade:** estas fontes descrevem funcionalidades públicas na data de consulta; podem mudar. Manter `docs/FEATURE_PARITY.md` com linhas `referência | módulo FauvoVet | detalhamento | prioridade | estado | evidência de teste`, sem alegar equivalência concluída até haver implementação e testes. Revisar periodicamente a referência oficial e registrar diferenças, sem depender de acesso não autorizado a produto concorrente.

### 2.1 Mapa mestre de módulos e funcionalidades

A cobertura **total é o backlog de produto**, não uma promessa de que tudo fará parte do primeiro MVP.

#### A. Administração SaaS e tenants

- Cadastro e onboarding de clínica/profissional; perfil Pessoa Física/Pessoa Jurídica.
- Isolamento por tenant, nome fantasia, CNPJ/CPF quando necessário, endereço, CRMV dos responsáveis e configurações.
- Tenant com uma ou mais unidades/filiais; dados e permissões por unidade quando configurados.
- Planos e assinaturas, período experimental, limites de usuários, módulos disponíveis, consumo, cobrança e bloqueios graduais.
- Painel **Superadmin FauvoVet** para configuração comercial, disponibilidade e suporte auditado, **sem acesso automático aos prontuários**.
- Administração local: usuários, convite, recuperação de acesso, funções e permissões granulares.
- Parametrização de idioma, fuso, horários, feriados, duração da consulta e serviços.
- Logs de auditoria, sessões, dispositivos, MFA para perfis privilegiados e políticas de retenção.
- Exportação estruturada de dados pertencentes ao tenant, exclusão quando legalmente cabível e encerramento de conta com política documentada.

#### B. Responsáveis, clientes e pacientes

- Responsáveis pelo animal: cadastro, contatos, CPF/CNPJ quando necessário, endereço, preferências e consentimentos.
- Pacientes: nome, espécie, raça, sexo, nascimento/idade estimada, peso histórico, pelagem, identificação, microchip, castração, foto, sinais particulares e alergias.
- Suporte amplo a espécies **caninas, felinas, aves, répteis, pequenos mamíferos, animais exóticos e outras**, com formulários parametrizáveis.
- Vínculos muitos-para-muitos: um animal pode ter mais de um responsável autorizado e um responsável pode ter vários animais.
- Contatos de emergência, veterinário de referência, convênios, indicações, tags e observações restritas.
- Histórico longitudinal integrado: atendimentos, diagnósticos, receitas, vacinas, exames, procedimentos, anexos, pagamentos e internações.
- Prevenção de duplicidades, mesclagem auditada de cadastros, arquivamento e gestão de óbitos.
- Cadastro rápido durante agendamento, com complementação posterior.

#### C. Agenda clínica e serviços

- Calendário por dia, semana, mês, unidade, veterinário, sala, tipo de atendimento e recurso.
- Gestão de turnos e escalas, bloqueios, férias, sobreposição, encaixes e horários recorrentes.
- Tipos e durações de atendimento, status (agendado, confirmado, aguardando, em atendimento, concluído, faltou, cancelado).
- Confirmações, lista de espera, reagendamento, retornos e lembretes automatizados.
- Check-in, triagem e fila de espera com indicadores de tempo até atendimento.
- Agenda específica para atendimento domiciliar, com endereço e deslocamento quando contratado.
- Registro de observações para recepção e comunicação interna com controle de acesso.
- Agendamentos recorrentes, inclusive banho e tosa quando módulo habilitado.

#### D. Atendimento clínico e prontuário

- Histórico médico longitudinal, ficha de consulta, anamnese, queixa principal, histórico, exame físico e sinais vitais.
- Hipóteses diagnósticas versus diagnósticos confirmados; códigos/taxonomias quando aplicáveis, sinônimos e classificação clínica.
- Evolução clínica por data, hora, autor e unidade; SOAP opcional.
- Condutas, plano terapêutico, prescrições, procedimentos, encaminhamentos e retorno.
- Alergias, contraindicações, medicamentos atuais, peso/evolução, antecedentes e alertas clínicos importantes.
- Modelos estruturados para especialidades: clínica geral, oftalmologia, dermatologia, odontologia, ortopedia e medicina de animais exóticos, com expansão futura.
- Anexos de imagens, vídeos quando permitido, exames e documentos.
- Controle de bloqueio/assinatura de eventos; **não permitir reescrita silenciosa de registro finalizado**. Correções como adendos versionados e auditados.
- Linha do tempo, resumo clínico, emissão e exportação de documentos.
- Operação clínica vinculada ao caixa/financeiro com informação transparente ao usuário.
- Campos customizados por tenant e por tipo de atendimento sem romper o modelo central.

#### E. Vacinação, antiparasitários e medicina preventiva

- Catálogo configurável de vacinas, protocolos por espécie/faixa etária, doses, reforços e periodicidade.
- Registro do lote, fabricante, validade, local de aplicação, responsável e próximas datas.
- Carteira digital, calendário vacinal, alertas de atraso e convocações autorizadas.
- Vermifugação, prevenção de ectoparasitas e outros procedimentos preventivos.
- Integração opcional com estoque, consumo e rastreabilidade do produto utilizado.

#### F. Exames, laudos e imagens

- Solicitação de exames e painel por status: solicitado, coletado, em análise, recebido, revisado.
- Catálogo de exames, atributos e intervalos de referência por espécie e critérios relevantes.
- Upload de PDFs, DOCX, imagens e resultados; armazenamento original preservado.
- Laudos próprios, interpretação e assinatura por profissional autorizado.
- Histórico comparativo de exames e gráficos de parâmetros clínicos quando houver dados estruturados.
- Integração futura com laboratórios por API ou arquivos padronizados, sem supor integrações disponíveis.
- Indexação e busca em resultados com texto pesquisável, sempre identificando a origem e a qualidade da extração.

#### G. Documentos, receituários e autorizações

- Modelos de receitas, fórmulas, atestados, laudos, encaminhamentos, solicitações, orientações e declarações.
- Termos de consentimento, anestesia, cirurgia, internação, alta e retirada sem alta.
- Modelos globais do produto, modelos exclusivos do tenant, modelos por especialidade e favoritos do usuário.
- Inserção controlada de dados do paciente, responsável, profissional, clínica e atendimento.
- Geração em PDF com identidade da clínica e suporte a assinatura eletrônica conforme exigência aplicável.
- Histórico de documentos emitidos e suas versões, com PDF final preservado.
- Fluxo detalhado de importação e customização definido na seção 4.

#### H. Internação e hospitalização

- Admissão, triagem, nível de prioridade e registro de alergias, pertences, contato e autorização.
- Gestão de boxes/leitos: livre, ocupado, bloqueado, limpeza; responsáveis por turno.
- Evoluções de internação, sinais vitais, parâmetros clínicos, mapa assistencial e passagem de plantão.
- Prescrição de medicamentos e fluidoterapia; agenda de horários, execução, responsável e registro de desvios.
- Registro de procedimentos e enfermagem, consumos vinculados ao estoque, custos e faturamento.
- Alertas de doses atrasadas e pendências visíveis, sem automação de decisão clínica.
- Previsão de alta, alta formal, orientações, transferência e histórico integral pós-alta.

#### I. Cirurgia e procedimentos

- Pré-operatório, checklist, avaliação pré-anestésica, consentimento e agendamento de sala/equipe.
- Procedimento, equipe, protocolos utilizados, materiais, intercorrências e recuperação anestésica.
- Registro de tempos e checklist de segurança adaptável.
- Pós-operatório, documentos e acompanhamento.

#### J. Vendas, PDV, orçamento e comissões

- Catálogo de produtos e serviços, listas de preço, orçamento, kits/pacotes, descontos e regras de aprovação.
- Ponto de venda vinculado ou não ao atendimento, meios de pagamento, pagamentos parciais e parcelados.
- Abertura/fechamento de caixa, sangria, suprimento, devoluções e consulta de vendas.
- Conta do cliente, crédito/saldo, recebimentos, inadimplência e cobranças.
- Comissionamento por serviço, profissional, produto e critérios configuráveis.
- Integração opcional com adquirentes e gateways, com idempotência e conciliação.

#### K. Estoque e compras

- Produtos, fornecedores, marcas, grupos, código de barras, preços e custos.
- Saldo por unidade/local, lote, validade, estoque mínimo e previsão de reposição.
- Compras, pedidos de compra, recebimento e análise de fornecedores.
- Saídas por venda, uso interno, perdas, aplicações clínicas, ajustes e transferências.
- Inventário por celular/câmera ou leitor, com trilha de auditoria e reconciliação.
- Alertas de vencimento, produtos parados, excesso e sugestão de compras.

#### L. Gestão financeira e fiscal

- Contas a pagar e a receber, centros de custo, categorias, contas, bancos e cartões.
- Fluxo de caixa projetado e realizado; demonstrativos, receitas/despesas e inadimplência.
- Conciliação financeira, taxas de adquirentes e indicadores por serviço, profissional e unidade.
- Emissão de documentos fiscais (NFS-e, NFC-e, NF-e quando cabíveis) **por integração com provedor fiscal compatível** e após validação tributária; nunca alegar emissão fiscal real por apenas gerar um PDF.
- Perfis tributários e parâmetros por estabelecimento com validação contábil/jurídica.

#### M. Comunicação, fidelização e relacionamento

- E-mail, WhatsApp Business oficial e SMS mediante provedores e credenciais configurados.
- Lembretes de consulta, retorno, vacinas, vermífugos, exames e pós-atendimento.
- Mensagens de aniversário, boas-vindas e campanhas segmentadas com consentimento e descadastro.
- Templates de mensagens, agendamento de campanhas, fila, status de envio, custos e falhas.
- Histórico de interações, NPS/pesquisa de satisfação e indicadores de recorrência.
- Portal do responsável pelo animal: pets, agendamentos, comprovantes, receitas/documentos liberados, vacinas e comunicações.
- Site/página institucional configurável por clínica como módulo futuro, sem confundir com aplicação interna.

#### N. Banho, tosa e pet shop (módulos opcionais)

- Agenda de banhistas/tosadores, pacotes, recorrência e status operacional.
- Checklist de entrada/saída, observações, alergias e restrições do animal.
- Consumo interno, venda adicional, comissões, controle de tempo e relatórios.

#### O. Indicadores e inteligência gerencial

- Dashboard operacional: agenda, faltas, fila, atendimentos, retornos, vacinas e internações.
- Dashboards financeiro/comercial, receita por período, ticket médio, produtividade e recorrência.
- Estoque: giro, perdas, validade e rupturas.
- Relatórios filtráveis, exportáveis (CSV/XLSX/PDF), sempre respeitando escopo de acesso.
- Painéis específicos de performance clínica em dados **agregados ou adequadamente pseudonimizados**, quando pertinente.
- Construtor de relatórios segmentados com filtros compostos, favoritos e agendamento futuro.

---

## 3. Diferencial prioritário: Busca Clínica Inteligente

**Esta é uma capacidade de primeira classe, não um filtro comum aplicado à lista de pacientes.** Deve permitir localizar pacientes em toda a base do tenant e recuperar conteúdo clínico, inclusive de atendimentos antigos e documentos importados, com evidência da origem do resultado.

### 3.1 Fontes indexadas

- Responsáveis: nome, telefone, e-mail, CPF quando a permissão permitir.
- Pacientes: nome, espécie, raça, identificadores, tags e campos estruturados.
- Prontuários: anamnese, queixa, evolução, sinais/sintomas, conduta, procedimentos e observações autorizadas.
- Diagnósticos estruturados: condição, status (suspeito, confirmado, descartado), código, data, profissional.
- Medicamentos, alergias, vacinação, procedimentos e encaminhamentos.
- Exames: título, resultados estruturados, laudos textuais e texto extraído de anexos.
- Documentos clínicos anexados: conteúdo textual de PDF/DOCX/DOC convertido com controle de qualidade.
- **Não indexar indiscriminadamente dados administrativos sensíveis ou notas privadas:** respeitar a classificação de cada campo e o perfil autorizado.

### 3.2 Experiência de pesquisa

- Campo único de busca com autocompletar e sugestões, disponível globalmente na aplicação.
- Busca por nome/trecho de texto, expressões exatas, erros comuns, acentos, singular/plural e variações ortográficas.
- Facetas combináveis: espécie, raça, sexo, idade, unidade, profissional, datas, tipo de atendimento, diagnóstico, status diagnóstico, sintoma, tratamento, vacina, medicamento, exame, alergia, internação, tag e origem do conteúdo.
- Operadores lógicos simples na interface (`E`, `OU`, `NÃO`), intervalos de datas e filtros incluídos/excluídos.
- Busca combinada entre campos estruturados e texto livre, com diferenciação visual clara.
- Ordenar por relevância, data mais recente, data mais antiga e nome do paciente.
- Salvar filtros, pesquisas favoritas e visualizações de coortes clínicas privadas ao tenant.
- Mostrar **qual paciente**, **em que documento/consulta**, **em que data** e **qual trecho coincide**; destacar palavras correspondentes.
- Ao clicar, abrir o atendimento ou documento no contexto original, respeitando as permissões.
- Paginação/cursor, contagem de resultados, filtros persistidos na navegação e exportação autorizada.
- Não fazer busca em dados de outro tenant, nem em sugestão/autocomplete, cache, relatórios, embeddings ou logs.

### 3.3 Exemplos obrigatórios de aceitação

1. `dermatite` → pacientes cujos registros autorizados contenham esse termo; mostrar contexto.
2. `diagnóstico confirmado: dermatite atópica` + `espécie: cão` → somente casos confirmados da espécie filtrada.
3. `suspeita de doença renal` + `período: últimos 12 meses` → separar suspeitas dos casos confirmados.
4. `uveíte` + `espécie: ave` → recuperar registros de atendimento e texto extraído de laudo anexado.
5. `alergia: penicilina` + `tratamento: antibiótico` → cruzar campos clínicos distintos e sinalizar origem.
6. `recusa alimentar` OR `não se alimenta` → suportar expressões equivalentes por dicionário de sinônimos configurável; nunca inventar correspondência clínica sem fonte.
7. Uma busca feita no tenant A **jamais** encontra qualquer dado criado no tenant B, mesmo com ID ou URL manipulados.
8. Usuário sem permissão clínica não visualiza trechos do prontuário em resultados, sugestões, exportações ou contadores sensíveis.
9. Após salvar um atendimento ou processar um documento, a indexação é rastreável, recuperável e eventual atraso é informado.
10. Documentos não extraíveis aparecem com status `sem texto pesquisável`, não como indexados com sucesso.

### 3.4 Estratégia de implementação

**Primeira versão:** PostgreSQL `tsvector` com configuração para português, normalização para acentos, `pg_trgm`, índices GIN, filtros estruturados e ranking por campo. Usar busca parametrizada, sem concatenar SQL inseguro; separar documentos de índice por tenant e classificação de acesso.

**Fase de escala:** avaliar OpenSearch/Elasticsearch somente após métricas indicarem necessidade. Deixar um contrato de serviço de pesquisa (`ClinicalSearchService`) independente do mecanismo.

**Busca semântica assistida (futura e opcional):** pode sugerir sinônimos e conceitos, sempre apresentando evidências do prontuário, data, autor e confiança. **IA não confirma diagnósticos, não prescreve e não substitui julgamento veterinário.** Nunca enviar prontuários a terceiros/modelos sem base legal apropriada, avaliação de privacidade e habilitação explícita do tenant. Não contaminar resultados por dados de outros tenants.

### 3.5 Qualidade, segurança e desempenho

- Testes de relevância com vocabulário clínico em português, espécies diversas e siglas.
- Logs e métricas de latência sem registrar conteúdo bruto de prontuários por padrão.
- Metas iniciais **a validar em benchmark**, por exemplo p95 menor que 800 ms em consultas comuns com um conjunto de dados representativo; não prometer este valor antes da medição.
- Índices atualizados por eventos com reprocessamento idempotente; deletar ou desindexar quando aplicável.
- Proteção contra enumeração de pacientes, rate limiting e consultas demasiadamente caras.
- Filtros de acesso aplicados **na seleção dos resultados e na obtenção do documento**, não só no frontend.

---

## 4. Templates e documentos importados: personalização por tenant

Este módulo deve permitir que cada clínica ou veterinário **importe documentos já utilizados**, adote-os como padrões do tenant e os reutilize diretamente em qualquer cadastro/atendimento apropriado.

### 4.1 Branding personalizado

- Logo e favicon próprios do tenant, nome comercial, assinatura institucional, cabeçalho/rodapé, endereço, contatos e CRMV.
- Cores primária/secundária, temas claro/escuro, fontes aprovadas, espaçamentos e pré-visualização.
- Personalização de PDFs e documentos gerados, inclusive timbre e rodapé.
- Opções de identidade por unidade, com herança da configuração do tenant.
- Garantir contraste/acessibilidade independentemente das cores escolhidas (ajustar sugestões ou indicar conflito).
- Definir tokens de design e temas; **não** duplicar código CSS por cliente.

### 4.2 Importar documentos como modelos

Tipos inicialmente suportados:

- `.pdf`: preservar arquivo original. Para PDF com formulário, permitir mapear campos; para PDF sem campos editáveis, permitir variáveis sobrepostas ou conversão assistida para template editável com revisão humana.
- `.docx`: importar conteúdo/layout e mapear placeholders para um modelo editável, preservando original.
- `.doc` legado: converter em processo isolado e seguro, com revisão do resultado; informar possíveis diferenças de formatação.
- Imagens e PDF digitalizado: OCR apenas em pipeline separado e **resultado sempre revisável**, sem modificar o original.

Fluxo esperado:

1. Usuário autorizado importa o documento.
2. Sistema valida extensão real/MIME, tamanho, malware e limites; registra quem importou e quando.
3. Original é armazenado em repositório privado e criptografado, com hash e versão.
4. Extrair/convertê-lo quando tecnicamente viável, registrando sucessos e limitações.
5. Usuário associa categoria (receita, prontuário, anamnese, laudo, consentimento, termo etc.) e configura variáveis.
6. Usuário pré-visualiza e **publica como padrão do tenant**, da unidade ou da especialidade.
7. Ao abrir qualquer paciente/atendimento, o sistema oferece automaticamente os modelos permitidos por contexto e função.
8. Usuário preenche, personaliza por atendimento, gera PDF e assina conforme modalidade suportada.
9. Documento emitido fica vinculado ao paciente/atendimento e seu conteúdo final é preservado.
10. Alterações posteriores no modelo geram **nova versão**, sem modificar documentos já emitidos.

**Atenção:** importar um PDF não significa conseguir convertê-lo com fidelidade absoluta em um formulário editável; a interface deve mostrar claramente o que é original, convertido, revisável ou não convertível.

### 4.3 Variáveis e conteúdo padrão

Exemplos de placeholders (whitelist, nunca código arbitrário):

- `{{clinica.nome}}`, `{{clinica.logo}}`, `{{clinica.crmv}}`
- `{{unidade.endereco}}`, `{{profissional.nome}}`, `{{profissional.crmv}}`
- `{{responsavel.nome}}`, `{{responsavel.documento}}`
- `{{paciente.nome}}`, `{{paciente.especie}}`, `{{paciente.raca}}`, `{{paciente.peso_atual}}`
- `{{atendimento.data}}`, `{{atendimento.queixa}}`, `{{atendimento.conduta}}`

Usar editor seguro de templates, visualização de dados substituídos, validação de campos obrigatórios e escape para evitar XSS/HTML injection. Não inserir automaticamente textos clínicos não registrados ou não validados pelo veterinário.

### 4.4 Assinaturas

Distinguir rigorosamente:

1. **Assinatura visual/timbre:** imagem incorporada ao layout; por si só não comprova identidade ou integridade jurídica.
2. **Assinatura eletrônica:** captura de aceite e evidências conforme caso de uso e requisitos legais.
3. **Assinatura digital com certificado:** integração apropriada (incluindo ICP-Brasil quando exigida), validação de integridade e registro de evidências.

Regras de assinatura dependem do documento, atendimento presencial/remoto e legislação aplicável. Não rotular simples inserção de imagem como assinatura digital válida. Priorizar integração com provedor especializado e validação jurídica antes de entrar em produção.

### 4.5 Permissões e herança

- `global`: modelos básicos de referência do produto, sem dados de pacientes.
- `tenant`: modelos criados/adotados pelo administrador da clínica.
- `unidade`: sobreposição opcional por estabelecimento.
- `especialidade`: modelos filtrados pelo contexto clínico.
- `usuario`: favoritos e preferências; alterações em um modelo publicado exigem permissão específica.
- Defaults herdáveis, publicação/despublicação, rascunho, revisão e versionamento.
- A busca de modelos, visualização e acesso aos arquivos respeitam tenant e permissões.

---

## 5. Modelo multi-tenant e autorização

### 5.1 Conceitos

- **Tenant:** organização clínica ou profissional autônomo, proprietário lógico dos dados.
- **Unidade:** estabelecimento ou local de atendimento pertencente ao tenant.
- **Usuário:** identidade autenticável que pode possuir vínculo com um ou mais tenants, mas deve selecionar contexto explicitamente.
- **Vínculo:** usuário, tenant, unidade(s), papel(is), status e permissões.
- **Paciente/Responsável:** entidades pertencentes a um tenant, sem compartilhamento automático entre clínicas.
- **Superadmin:** operador FauvoVet com administração da plataforma, separado de acesso clínico.

### 5.2 Papéis iniciais

- `platform_admin`: saúde da plataforma, provisionamento, faturamento SaaS; sem leitura clínica por padrão.
- `tenant_owner`: titular/administrador máximo do tenant.
- `tenant_manager`: gestão operacional/comercial configurada.
- `veterinarian`: atendimento clínico e prontuário, sob política de unidade.
- `clinical_assistant`: funções clínicas delegadas, sem autonomia para atos restritos ao veterinário.
- `receptionist`: agendamentos, cadastro e informações necessárias; sem acesso automático a dados clínicos detalhados.
- `finance`: contas, vendas e demonstrativos, sem acesso automático à ficha clínica.
- `stock`: estoque/compras, sem acesso automático ao prontuário.
- `pet_services`: banho/tosa e checklists pertinentes.
- `client_portal`: responsável pelo animal, com acesso apenas aos próprios vínculos/documentos compartilhados.

Implementar **RBAC + regras contextuais** (tenant, unidade, participação no caso, estado do prontuário, tipo de documento). Negar por padrão. As permissões precisam ser verificadas no backend e no banco quando cabível.

### 5.3 Isolamento e banco de dados

**Recomendação inicial:** PostgreSQL compartilhado com colunas `tenant_id` e **Row-Level Security (RLS)**, implementada com políticas `USING` e `WITH CHECK` em todas as tabelas clínicas/comerciais aplicáveis. Acesso da aplicação sem privilégios de bypass de RLS; migrações com usuário administrativo separado.

- Todas as chaves/índices únicos relevantes incluem o escopo do tenant.
- Chaves estrangeiras devem impedir relacionamentos acidentais entre tenants, quando aplicável via FKs compostas.
- Autenticação determina tenant ativo por sessão/vínculo validado, nunca confiar cegamente no `tenant_id` enviado no corpo ou query string.
- Contexto RLS estabelecido de forma transacional, protegido contra vazamento por pool de conexões.
- Serviços assíncronos recebem tenant e identidade/escopo de execução validados.
- Armazenamento de arquivos usa caminhos/objetos e ACLs privados por tenant; acesso por URL assinada de curta duração, após checagem de permissão.
- Logs, caches, filas, exports, dados agregados, ferramentas de suporte, backups e índices de busca devem preservar o isolamento.
- Testes obrigatórios com pelo menos dois tenants para **todas** as rotas, buscas, anexos, exports, jobs e recursos de analytics.
- Projetar possibilidade futura de migrar tenants grandes para banco isolado sem alterar a API pública.

---

## 6. Arquitetura técnica recomendada

Esta é a direção inicial. Antes de alterar, documentar decisão arquitetural (ADR) com benefício, custo, risco e compatibilidade com Locaweb.

### 6.1 Stack sugerida

| Camada | Proposta inicial | Critério |
|---|---|---|
| Web | Next.js + React + TypeScript | Aplicação responsiva, roteamento e componentes modernos |
| UI | Tailwind CSS + shadcn/ui/Radix + ícones de licença compatível | Design system acessível e tematização por tenant |
| API | NestJS (Node.js/TypeScript) | Domínios, autorização e contratos claros |
| ORM | Prisma ou Drizzle, após avaliar RLS e migrações | Tipagem, migrações e consultas seguras |
| Dados | PostgreSQL | Consistência, JSONB, índices, full-text search, RLS |
| Cache/fila | Redis + BullMQ (ou equivalente) | Processamento de PDFs, notificações e buscas |
| Objetos | S3 compatível privado, local de testes ou serviço contratado | Anexos, relatórios e documentos |
| Documentos | Extratores PDF/DOCX, conversor DOC isolado, geração PDF | Importação, templates e PDFs emitidos |
| Autenticação | Serviço consolidado com sessões seguras, RBAC e MFA | Segurança, suporte a multi-tenant |
| Testes | Vitest/Jest, Playwright, testes de API e isolamento | Confiabilidade |
| Deploy | Docker Compose inicial em VPS Locaweb; proxy reverso e TLS | Controle operacional e manutenção |
| Observabilidade | OpenTelemetry, logs estruturados, métricas e alertas | Suporte e performance |

Manter o **frontend e o backend desacoplados por contratos tipados**. Não exagerar em microsserviços na primeira entrega. Preferir monorepo organizado e serviços de backend modulares.

### 6.2 Estrutura sugerida

```text
fauvovet/
├── CLAUDE.md
├── README.md
├── CONTRIBUTING.md          # execução, contribuições e convenções multiplataforma
├── LICENSE                  # licença open-source explícita; sugestão MIT
├── .env.example
├── .gitattributes            # finais de linha e scripts consistentes
├── apps/
│   ├── web/                 # Next.js / design system
│   ├── api/                 # NestJS / regras de negócio
│   └── worker/              # filas, extração e indexação
├── packages/
│   ├── ui/                  # componentes reutilizáveis e temas
│   ├── contracts/           # DTOs / schemas / tipos públicos
│   ├── config/              # lint, tsconfig, configurações
│   └── shared/              # utilitários não clínicos
├── infra/
│   ├── docker/
│   ├── nginx/
│   └── scripts/
├── docs/
│   ├── ARCHITECTURE.md
│   ├── FEATURE_PARITY.md
│   ├── DATA_MODEL.md
│   ├── SECURITY.md
│   ├── DEPLOY_LOCAWEB.md
│   ├── ROADMAP.md
│   ├── DECISIONS.md
│   ├── IDEAS.md
│   ├── TESTING.md             # comandos, ambientes, critérios e testes de regressão
│   ├── PROGRESS.md            # ponto de retomada sempre atualizado
│   └── changes/
│       ├── TEMPLATE.html      # template do runbook por entrega
│       └── AAAA-MM-DD-descricao.html  # um guia para cada mudança implantável
├── scripts/                  # CLI portável para bootstrap, verify, build e smoke
├── .github/workflows/        # testes automatizados + inspeção de documentação
└── tests/
    ├── e2e/
    ├── isolation/
    └── fixtures/
```

Criar diretórios/arquivos somente quando forem necessários. Evitar skeletons gigantes sem código real.

### 6.3 Modelo de dados inicial sugerido

Entidades de plataforma: `Tenant`, `TenantPlan`, `Subscription`, `Unit`, `User`, `Membership`, `Role`, `Permission`, `UserSession`, `AuditEvent`, `TenantTheme`.

Entidades clínicas: `Guardian`, `Patient`, `GuardianPatientLink`, `Appointment`, `Encounter`, `ClinicalNote`, `Diagnosis`, `Symptom`, `EncounterDiagnosis`, `Procedure`, `Prescription`, `Medication`, `VaccineProtocol`, `Vaccination`, `ExamOrder`, `ExamResult`, `Hospitalization`, `HospitalBed`, `CareTask`, `Surgery`, `Attachment`, `ClinicalDocument`.

Documentos/modelos: `DocumentTemplate`, `TemplateVersion`, `TemplateAssignment`, `TemplateVariable`, `GeneratedDocument`, `SignatureEvidence`, `DocumentExtractionJob`.

Comerciais: `Product`, `Service`, `PriceList`, `InventoryLot`, `InventoryMovement`, `StockCount`, `PurchaseOrder`, `Sale`, `SaleItem`, `Payment`, `Receivable`, `Payable`, `CashRegister`, `Commission`, `FiscalDocument`.

Busca/comunicação: `ClinicalSearchIndex`, `SavedSearch`, `SearchSynonym`, `NotificationTemplate`, `NotificationEvent`, `MessageDelivery`, `Campaign`, `ConsentRecord`.

Para cada entidade:

- Definir ownership, campo `tenant_id` se aplicável, FKs e política de retenção.
- Usar IDs gerados no servidor, timestamps com timezone e exclusão lógica somente onde apropriado.
- Evitar exclusão física/edição indevida de eventos clínicos e documentos assinados.
- Registrar autor, origem, timestamps e versões em dados clínicos.
- Segregar textos originais, conteúdos extraídos, metadados, fatos estruturados e sugestões não validadas.
- Nunca misturar `diagnóstico registrado` com `possível diagnóstico sugerido por IA`.

---

## 7. UX/UI: padrão premium e mobile-first

**Posicionamento visual:** software premium de saúde veterinária, confiável e refinado; navegação clara para reduzir tempo de digitação durante atendimento. Design original, sem aparência de painel genérico de admin.

### 7.1 Diretrizes

- Responsivo de verdade em desktop, tablet e celular, sem tabelas inutilizáveis no mobile.
- Dashboard inicial diferente por função (veterinário, recepção, gestão, financeiro).
- Menu lateral no desktop e navegação condensada no celular; ações frequentes sempre acessíveis.
- Tipografia legível; espaçamentos consistentes; hierarquia visual; cards só quando agregarem clareza.
- Comandos contextuais para novo agendamento, paciente, atendimento, receita e pesquisa.
- Busca global em destaque, com filtros avançados em painel lateral/drawer no mobile.
- Ficha do paciente com resumo, alertas de alergias, histórico cronológico e abas objetivas.
- Preservar rascunhos de consulta em eventuais falhas de conexão, sem sobrescrever registros clínicos confirmados.
- Estados completos: carregando, vazio, falha, sem permissão, offline parcial, salvando, salvo, conflito de edição.
- Feedback visível de ações, confirmações para mudanças clínicas importantes e boas mensagens de validação.
- Contraste conforme WCAG 2.2 AA como meta; navegação por teclado, labels, foco visível e suporte a leitor de tela.
- Preferir interface rápida e estável a animações excessivas.

### 7.2 Tema padrão FauvoVet

Criar identidade própria baseada em uma paleta sóbria, tipografia moderna e toques sutis que remetam a cuidado e tecnologia. Este tema é **apenas o padrão**: cada tenant pode personalizar logo, nome, cores e apresentação dos seus documentos. Salvar personalização como tokens de tema por tenant e restaurar padrão quando desejado.

### 7.3 Fluxos de referência (devem ser demonstráveis)

1. Entrar → selecionar tenant/unidade → dashboard contextual.
2. Cadastrar responsável e paciente → agendar → check-in → atendimento → receita/documento → finalizar.
3. Pesquisar termo clínico → filtrar diagnóstico confirmado + espécie → localizar paciente → abrir trecho original.
4. Importar DOCX/PDF → configurar variáveis → publicar modelo → gerar documento no prontuário → preservar versão.
5. Registrar vacina → atualizar histórico e estoque → agendar reforço → programar lembrete consentido.
6. Admitir internação → prescrever horários → marcar execução → alta e orientações.

---

## 8. Segurança, LGPD e regulação veterinária

O produto tratará dados pessoais dos responsáveis e informações clínicas potencialmente confidenciais. **Privacidade e conformidade são requisitos de arquitetura.** Não considerar o sistema juridicamente aprovado sem análise profissional antes de comercializar.

### 8.1 Medidas obrigatórias

- HTTPS/TLS em todas as rotas; senhas com hash robusto (Argon2id ou equivalente), proteção contra força bruta e MFA privilegiado.
- Sessão com cookies `HttpOnly`, `Secure`, `SameSite` apropriado e escopo de caminho/domínio correto.
- CSRF para fluxos autenticados por cookie, proteção contra XSS/SQLi/SSRF e validação rigorosa de arquivos.
- Criptografia em trânsito e em repouso onde pertinente; chaves e segredos fora do repositório.
- Permissões por rota, operação, tenant, unidade e documento.
- Auditoria de visualização/acesso a prontuários, criação, atualização, assinatura, exportação e atos administrativos sensíveis.
- Logs sem tokens, credenciais, anexos ou dados clínicos brutos.
- Backups criptografados, restauração testada, política de retenção, monitoramento e resposta a incidentes.
- Ambientes dev/homologação com dados sintéticos; anonimizar antes de usar qualquer dado real em teste.
- Contratos e políticas: termos de uso, política de privacidade, consentimentos específicos quando necessários, controlador/operador, direitos do titular, fornecedores/suboperadores.
- Acessos de suporte somente com justificativa, autorização explícita da clínica quando envolver registros sensíveis, temporalidade e trilha de auditoria.
- Portabilidade/exportação do tenant e mecanismos para atender solicitações legítimas dos titulares, observadas obrigações de guarda.

### 8.2 Normas a verificar antes da publicação

- **LGPD — Lei 13.709/2018** e orientações aplicáveis da ANPD.
- **CFMV Resolução 1.321/2020**, com alterações posteriores, inclusive **Resolução 1.653/2025**, para documentação médica-veterinária e prontuários.
- Regras de prescrição e assinatura, inclusive telemedicina quando a funcionalidade estiver habilitada (CFMV Resolução 1.465/2022 e atualizações).
- Regras de atendimento domiciliar e documentação quando aplicáveis (CFMV Resolução 1.690/2026 e alterações).
- Normas federais/municipais/estaduais pertinentes à emissão fiscal, receitas de controle especial e atividade veterinária.

Fontes orientadoras:

- https://www.cfmv.gov.br/retirada-de-pets-sem-alta-medica-exige-termo-de-responsabilidade-reafirma-cfmv/comunicacao/noticias/2025/07/03/
- https://www.cfmv.gov.br/resolucao-do-cfmv-regulamenta-a-telemedicina-veterinaria/comunicacao/noticias/2022/06/29/
- https://www.cfmv.gov.br/medico-veterinario-em-casa-ate-onde-vai-o-atendimento-domiciliar/comunicacao/noticias/2026/08/14/

**Regra central:** cada documento deve apresentar identificação do profissional, CRMV, datas e informações aplicáveis ao tipo de atendimento; não substituir exigências legais por campos opcionais. Prazos de guarda, requisitos de assinatura e disponibilização de cópias devem ser revisados juridicamente conforme normas vigentes.

---

## 9. Hospedagem: Locaweb e publicação em subdiretório

### 9.1 Requisito público

O endereço esperado **inicialmente** é:

```text
https://dradeboragakdino.com.br/fauvovet
```

- O caminho `/fauvovet` é **base path** da aplicação, não identificador de tenant.
- O frontend, APIs, assets, autenticação, cookies, redirects, uploads e webhooks precisam funcionar sem pressupor hospedagem na raiz `/`.
- Planejar posterior mudança para subdomínio dedicado (`app.dominio...`) por configuração e reverse proxy, sem reescrever a aplicação.
- Reservar `/fauvovet/api/...` para API pública; documentar rota e não deixar o Next.js capturá-la indevidamente.
- APIs e arquivos privados não devem ser publicados diretamente como pasta estática acessível.

### 9.2 Validar o plano Locaweb antes do deploy

A expressão “Hospedagem Locaweb” **não identifica** se o plano é compartilhado, VPS ou cloud. Antes do deploy, registrar em `docs/DEPLOY_LOCAWEB.md`:

- Plano contratado, acesso root/SSH, suporte a Node persistente, Docker e proxy reverso.
- Limites reais de CPU, RAM, espaço, processos, certificados e backups.
- PostgreSQL disponível/contratado e política de acesso a banco.
- DNS e TLS para o domínio, acesso ao virtual host e possibilidade de encaminhar `/fauvovet`.
- E-mail transacional e armazenamento privado de objetos.
- Protocolo disponibilizado para publicação com FileZilla: preferir **SFTP ou FTPS**, nunca enviar credenciais de FTP para o Git.

**Estratégia preferida:** VPS Linux Locaweb com Docker Compose contendo `web`, `api`, `worker`, `postgres`, `redis` e proxy HTTPS; armazenamento de anexos privado e backups fora da própria VPS. Ajustar capacidade da VPS a benchmark de memória/CPU/carga; não pressupor que a hospedagem compartilhada rode esse stack.

**Alternativa se o contrato permitir apenas hospedagem compartilhada:** avaliar frontend estático compatível e backend/database hospedados em infraestrutura separada, ou recomendar migração para VPS. Nunca declarar deploy como concluído sem confirmar que a infraestrutura suporta o stack.

Fonte indicativa Locaweb VPS: https://www.locaweb.com.br/servidor-vps/

### 9.3 Requisitos técnicos de publicação

- Configurar `basePath: '/fauvovet'` no build do Next.js quando esta estratégia for utilizada.
- Definir regras de proxy e cache sem reescritas ambíguas; validar caminhos e assets de bibliotecas.
- Configurar rotas `/fauvovet/api`, `/fauvovet`, healthchecks e redirects HTTPS; preservar headers seguros.
- Cookies autenticados com `Path=/fauvovet`, a menos que integração exija escopo distinto e justificado.
- Usar `.env.example` sem segredos; variáveis reais no gerenciador do ambiente.
- Deploy com migrações planejadas, backup prévio e possibilidade de rollback.
- Testar upload/consulta privada de anexo, login/logout, mudança de tenant, busca, PDF e funções mobile no domínio final.
- A URL informada pelo usuário é o **destino desejado**, não prova de que DNS, certificado, hospedagem ou sistema já estejam configurados.
- **FileZilla é um cliente de transferência, não um servidor de aplicações**: utilizar SFTP/FTPS para transferir somente os artefatos compatíveis com o plano (por exemplo, frontend estático, quando tecnicamente viável, ou pacotes e configurações preparados). Se o backend demandar processos Node, Docker, banco ou filas, implantá-los por SSH/CI/deploy na infraestrutura que realmente os suporte. Nunca tentar colocar o backend como simples conjunto de arquivos PHP/HTML nem expor `.env`, uploads privados ou banco em `public_html`.
- Criar `docs/DEPLOY_LOCAWEB.md` com instruções separadas para publicação via FileZilla, setup de backend, migrações e rollback; documentar diretório remoto e conectividade **somente após verificar os dados do plano**.
- **Não publicar em produção automaticamente**: o GitHub pode ser sincronizado sem aprovações por incremento, mas o deploy exige testes verdes, backup/migração quando aplicável e acionamento explícito de publicação pelo operador.

---

## 10. Observabilidade, operação e qualidade

FauvoVet será operado como serviço de produção, com gestão de confiabilidade desde a primeira fase.

- Logs estruturados com correlation ID, request ID e tenant **pseudonimizado** quando possível.
- Rastreamento distribuído de web → API → worker; instrumentação OpenTelemetry.
- Métricas: latência HTTP, erro 4xx/5xx, uso de recursos, filas, processamento de documentos, atraso de indexação, consultas clínicas e falhas de integração.
- Painel de saúde e alertas por indisponibilidade, falha de backup, espaço, banco, jobs repetidamente falhos e latência anormal.
- Rate limits, timeout, retries com backoff, dead-letter queue, idempotência em pagamentos e comunicações.
- Backups automáticos com testes periódicos de restauração; definir RPO/RTO antes da operação comercial.
- Testes unitários, integração, e2e com Playwright, segurança e isolamento cross-tenant em CI.
- Validar localmente antes de qualquer publicação; CI replica o mesmo conjunto obrigatório de verificações. O ambiente local deve ser isolado da produção, usar dados sintéticos e funcionar no WSL2/Git Bash dentro dos limites das dependências disponíveis.
- Medir latência p50/p95/p99, Core Web Vitals, custo das consultas, índices da busca e tamanho dos bundles; acompanhar variações por versão, perfil de carga e hardware documentados.
- Definir limites realistas com medição antes de afirmar níveis de serviço.
- Não utilizar registros clínicos de clientes como exemplo de telemetry ou fixture.

---

## 11. Plano de entrega: fases, critérios e dependências

**Todas as funcionalidades da seção 2 permanecem no escopo de longo prazo.** Implementar na sequência abaixo para entregar produto real desde cedo. Atualizar `docs/ROADMAP.md` e `docs/FEATURE_PARITY.md` após cada evolução.

### Fase 0 — Descoberta, fundação e infraestrutura de desenvolvimento

Entregas:

- Inspecionar o repo, criar arquitetura/ADR, convenções, monorepo, CI e `.env.example`.
- Design system inicial, navegação responsiva, temas e componentes acessíveis.
- Banco PostgreSQL, migrações, Docker local e ambiente de desenvolvimento reproduzível.
- Especificação de papéis/permissões, matriz de isolamento e política mínima de backup.
- Primeiras telas reais de login, onboarding e tenant.

**Aceite:** aplicativo e API sobem localmente; testes básicos executam; arquitetura e passos de execução documentados.

### Fase 1 — MVP clínico operável

Entregas:

- Cadastro de tenants, unidades, usuários, RBAC e RLS.
- Cadastro de responsáveis e pacientes (incluindo exóticos).
- Agenda, consulta, prontuário longitudinal, eventos clínicos e anexos privados.
- Biblioteca de modelos básicos, geração de PDF e templates por tenant.
- UI premium funcional em desktop/celular.

**Aceite:** fluxo cadastro → agenda → atendimento → documento funciona integralmente e está protegido entre dois tenants.

### Fase 2 — Diferencial de busca e importação documental

Entregas:

- Full-text search clínica com filtros compostos, relevância, destaque de trechos e navegação até origem.
- Parser seguro PDF/DOCX, importação DOC com conversão, fila e status.
- Upload de modelos da clínica, publicação, herança, placeholders, versão e geração final.
- Pesquisas salvas, testes de relevância e suite de isolamento da busca.

**Aceite:** todos os casos da seção 3.3 executam; nenhuma consulta cross-tenant é bem-sucedida; importação preserva o original e identifica possíveis perdas de conversão.

### Fase 3 — Consolidação clínica

Entregas:

- Vacinação, exames, prescrição, relatórios clínicos e processos avançados de atendimento.
- Internação, parâmetros, boxes, prescrição/execução e alta.
- Cirurgia/procedimentos e modelos especializados por área.
- Portal do responsável com compartilhamento seguro.

**Aceite:** operações clínicas funcionam sem planilhas paralelas nos fluxos suportados e dados persistem com auditoria.

### Fase 4 — Gestão comercial e operacional

Entregas:

- Estoque, compras, inventário, catálogo de serviços e produtos.
- Caixa/PDV, orçamento, cobrança, contas e comissões.
- Dashboard gerencial, relatórios e exportações.
- Recursos de pet shop, banho e tosa como módulos opcionais.

**Aceite:** movimentações são conciliáveis, auditáveis e vinculadas aos contextos pertinentes.

### Fase 5 — Integrações e SaaS comercial

Entregas:

- Provedores oficiais de e-mail, WhatsApp e SMS (respeitando consentimento e custos).
- Integrações de pagamentos/fiscal com sandbox e validação profissional.
- Planos SaaS, cobrança recorrente e administração de assinatura.
- Onboarding comercial, módulos habilitáveis, suporte e observabilidade operacional.
- Portal/site de clínica quando priorizado.

**Aceite:** cada integração possui teste real em sandbox do fornecedor, tratamento de erros, documentação e ativação controlada por tenant.

### Fase 6 — Evolução e inteligência

- Busca semântica controlada, sinônimos clínicos revisados por profissionais, análise de coortes autorizadas.
- Integrações com laboratórios, convênios/parceiros e redes de unidades mediante acordos.
- Ferramentas de produtividade clínica, sugestões de organização de registro e dashboards avançados.
- Otimizações de infraestrutura por dados de uso real.

**Aceite:** qualquer recurso assistido apresenta fonte, limites, consentimentos e nunca substitui decisão veterinária.

---

## 12. Regras de engenharia e entrega contínua

### 12.1 Código e integração

- TypeScript estrito, lint/formatter consistentes, nomes claros e validação de entrada/saída com schemas.
- API versionada, erros padronizados, paginação, filtros documentados e contratos estáveis.
- Domain boundaries explícitos; evitar dependência circular entre módulos.
- Commits pequenos, mensagens claras e documentação atualizada.
- Não introduzir dependências sem avaliar manutenção, licença, tamanho, segurança e compatibilidade.
- Em testes, usar fixtures sintéticas e cenários clínicos fictícios.
- Não colocar tokens, dados reais de pacientes, credenciais, chaves, `.env` reais ou PDFs clínicos identificáveis no Git.

### 12.2 Git, autonomia e sincronização obrigatória

**Repositório canônico:** `https://github.com/rafasilva1984/fauvovet` (público). O nome do remote deve ser preferencialmente `origin`, sem presumir o nome da branch padrão; verificar o Git. A finalidade pública se refere ao **código-fonte aberto**, jamais a dados, contas ou prontuários dos tenants.

O proprietário **autoriza expressamente o Claude Code a executar `git fetch`, `git pull`, `git add`, `git commit` e `git push` no projeto, sem pedir aprovação a cada operação**, inclusive preparar branch, inicializar repo e configurar `origin` caso necessário. Não interpretar essa autonomia como autorização para sobrescrever contribuições remotas, publicar dados reais ou alterar repositórios alheios.

**Fluxo obrigatório em TODA sessão e incremento funcional ou técnico:**

1. No início, executar `git status --short --branch`, `git remote -v`, `git fetch origin` quando o remote existir, conferir upstream e histórico recente. Se a working tree estiver limpa, integrar atualizações remotas **sem perder commits locais**; preferir `git pull --ff-only` quando aplicável. Se houver divergência, avaliar rebase/merge com segurança, sem sobrescrever alterações de terceiros. Se houver mudanças locais, inspecioná-las antes de integrar.
2. Ler `docs/PROGRESS.md` e os últimos HTML de `docs/changes/` antes de escolher a próxima tarefa.
3. Implementar **um incremento pequeno, testável e completo**. Executar `pnpm verify` ou os verificadores equivalentes já implementados, além dos testes específicos de regressão.
4. Criar **um HTML de execução/implantação e testes para o incremento** em `docs/changes/AAAA-MM-DD-slug.html` e atualizar `docs/PROGRESS.md`, matriz de funcionalidades e decisões afetadas.
5. Conferir o diff e procurar segredos, PHI/dados de tutores/pacientes, uploads, dumps e artefatos compilados. Executar o conjunto de qualidade obrigatória.
6. Executar `git add` seletivo, `git commit` descritivo e `git push` para a branch corrente configurada; **não solicitar confirmação** para essas operações normais. Integrar novidades remotas de modo seguro se o push for recusado por non-fast-forward, testar novamente e repetir sem usar força.
7. Confirmar sincronização (`git status -sb`, tracking branch, `git log`/`git rev-parse` e estado do remote). Se falhar por credencial, rede, conflitos ou branch protegida, preservar alterações, registrar bloqueio no checkpoint e informar comandos/causa. **Nunca afirmar push ou sincronização concluídos sem retorno de sucesso.**
8. Antes de parar por qualquer motivo (inclusive limite de contexto), atualizar checkpoint detalhado e tentar sincronizar alterações verificadas; registrar arquivos incompletos quando não for possível concluir a tarefa.

**Frequência:** todo progresso concreto resulta em ao menos um commit e uma tentativa de push após os gates locais; não acumular uma implementação extensa até o final. Evitar commits vazios e histórico artificial. Não publicar como concluído um incremento com testes falhando. Em caso de bloqueio, manter checkpoint explícito sem comprometer o branch estável com código quebrado.

**Restrições:** nunca executar `push --force`, `reset --hard`, apagar branches/commits, destruir alterações locais ou reescrever histórico compartilhado por conta própria. `pull` não significa descartar dados; preservar mudanças existentes de terceiros. Autenticações iniciais do GitHub podem exigir interação humana e devem ser comunicadas somente quando estritamente necessário.

### 12.3 Checkpoint obrigatório e retomada sem perda de contexto

Manter `docs/PROGRESS.md` **desde o primeiro commit**. Atualizar durante a sessão e após cada incremento, não apenas ao encerrar. Campos mínimos:

- Data/hora com timezone, objetivo da sessão, status geral do projeto e fase do roadmap.
- Branch/remote, último commit **já existente** e estado de sincronização; não tentar registrar dentro do próprio commit o SHA que ainda não existe.
- Módulos efetivamente implementados com indicação clara do que é mock, parcial ou concluído.
- Arquivos criados/modificados, mudanças em modelo de dados, migrações e flags.
- Comandos reais executados, resultados de testes e evidências; não inventar métricas.
- Dependências instaladas, versões relevantes e estado do ambiente WSL/Git Bash.
- Defeitos, riscos, decisões e bloqueios (credenciais ausentes sem armazená-las).
- **Próxima ação exata e reproduzível**, com comando ou arquivo de entrada, para que outra sessão continue sem precisar redescobrir o contexto.
- Link relativo para o guia HTML da mudança mais recente e, se aplicável, PR/issue associado.

### 12.4 Definição de pronto (DoD)

Uma funcionalidade só está **PRONTA** se atender simultaneamente a:

1. UI funcional e responsiva (quando aplicável), inclusive estados de erro e carregamento.
2. API/domínio/persistência implementados; nenhuma dependência simulada sem identificação.
3. Permissões verificadas no backend e isolamento multi-tenant testado.
4. Validação, testes automatizados essenciais e migrações revisadas.
5. Auditoria de atos sensíveis quando aplicável.
6. Documentação, `docs/PROGRESS.md` e feature matrix atualizadas.
7. **Arquivo HTML da mudança**, acessível offline, com passos para aplicar e testar tanto em WSL quanto Git Bash quando aplicável, além de recuperação/rollback.
8. Demonstração ou roteiro reproduzível de aceite com evidências e status dos testes locais obrigatórios.
9. Sem segredos ou dados reais desnecessários no repositório.
10. Inspeção básica de performance e mobile executada, com resultados/limitações informados.
11. `git commit` e `git push` bem-sucedidos na branch de trabalho, ou bloqueio de sincronização documentado explicitamente; o bloqueio impede declarar a entrega sincronizada ou publicada.

### 12.5 Não fazer

- Não transformar o produto em apenas um CRUD esteticamente bonito.
- Não criar consulta de busca ampla que só verifica nome/CPF do responsável.
- Não tratar hipótese diagnóstica como diagnóstico confirmado.
- Não alterar prontuário assinado sem trilha histórica.
- Não compartilhar modelos privados, anexos ou índices entre tenants.
- Não deixar autorização de clínica somente no frontend.
- Não declarar suporte a arquivo editável quando o PDF foi apenas armazenado.
- Não ativar envio comercial de WhatsApp sem consentimentos e provedor autorizado.
- Não prometer conformidade jurídica automaticamente.
- Não publicar informações sensíveis no diretório público da hospedagem.
- Não apresentar como entregue função prevista apenas para fases posteriores.
- Não fazer upload do backend ou dos dados sensíveis como se fossem arquivos estáticos acessíveis pelo navegador.
- Não usar produção como ambiente de testes nem transferir dados clínicos reais para um repositório público.
- Não prometer compatibilidade com Git Bash/WSL sem executar pelo menos testes smoke documentados nos ambientes disponíveis.

---

## 13. Primeira execução obrigatória do Claude Code

Ao abrir este projeto, executar a seguinte sequência, sem tentar implementar todo o sistema de uma vez:

1. **Diagnosticar o repositório**: listar conteúdo, dependências, arquitetura, ambiente WSL2/Git Bash e estado Git. Verificar/conectar `origin` a `https://github.com/rafasilva1984/fauvovet`, sem sobrescrever arquivos ou histórico.
2. **Criar `docs/PROGRESS.md`, `docs/TESTING.md` e `docs/changes/TEMPLATE.html` imediatamente**, mesmo sem código funcional; o primeiro checkpoint deve dizer honestamente que a implementação ainda não começou.
3. **Construir e atualizar a matriz de escopo** (`docs/FEATURE_PARITY.md`) com todos os módulos deste documento e prioridade/fase.
4. **Registrar decisões-base** de arquitetura, multi-tenant, RLS, arquivos e estratégia de deploy, com riscos conhecidos.
5. **Propor a primeira entrega executável**: monorepo, Docker local, login, tenant, RBAC inicial, tema e shell responsivo.
6. **Implementar essa entrega**, incluindo migrações, seeds falsos, API, UI e testes.
7. **Validar isolamento em dois tenants** mesmo antes de criar prontuários reais.
8. **Criar o PRIMEIRO runbook HTML** com todos os passos e testes concretos dessa entrega; nenhuma implementação está isenta da regra de documentação.
9. **Documentar como rodar** em WSL2/Git Bash no `README.md` e como validar futura publicação em `/fauvovet` via FileZilla quando aplicável.
10. **Executar os gates locais**, corrigir falhas, medir performance mínima e relatar o que permanece pendente.
11. **Atualizar checkpoint**, revisar diff/segredos, `commit` e `push` sem pedido de aprovação; verificar sincronização com `origin` e registrar o próximo passo exato.

**Orientação final:** trabalhe como se estivesse construindo uma empresa de software veterinário de longo prazo. Preserve facilidade de evolução, segurança dos registros, qualidade clínica e excelente experiência do profissional. Quando uma necessidade nova aparecer, proponha sua inclusão sem sacrificar isolamento entre tenants, auditabilidade ou integridade do histórico médico.


---

## 14. Publicação do código, licença e portabilidade de instalação

### 14.1 Projeto open-source e distribuição

- O **repositório oficial público** é `https://github.com/rafasilva1984/fauvovet`; tratá-lo como fonte canônica do código e documentação. O produto pode ser implantado por outras pessoas e em outras localidades/infraestruturas, não apenas na clínica original.
- Adotar licença open-source explícita no arquivo `LICENSE`; **recomendação inicial: MIT**, salvo decisão posterior do proprietário. Estar visível no GitHub **não** equivale, por si só, a conceder licença de reutilização. Conferir licenças de dependências, ícones, fontes e ativos visuais antes de redistribuir.
- `README.md` e `CONTRIBUTING.md` devem ensinar: pré-requisitos, clonagem, configuração sem dados privados, instalação, execução local, testes, build, arquitetura, suporte WSL/Git Bash, deploy e contribuição por PR.
- Nunca colocar no GitHub: pacientes reais, nomes/contatos de tutores, prontuários, imagens clínicas identificáveis, tokens, chaves privadas, URLs secretas, dumps do banco, backups, `.env` com valores reais, arquivos importados privados, credenciais Locaweb/FileZilla ou logs sensíveis. Criar `.gitignore`, verificações de segredo e fixtures totalmente sintéticas antes do primeiro push.
- Garantir por configuração que usuários e clínicas externas não herdem logos, assinaturas, dados médicos ou branding privado da clínica original. Branding inicial neutro do FauvoVet e customização por tenant.
- Implantação self-hosted deve permitir configurações próprias via variáveis de ambiente e provisionamento seguro, sem caminhos ou domínios hardcoded. O domínio `dradeboragakdino.com.br/fauvovet` é o destino **da primeira instalação**, não uma dependência obrigatória de outras instalações.
- Prever localização (`pt-BR` inicialmente), fuso por unidade, moeda configurável quando pertinente, limites de armazenamento e URL/basePath por ambiente, evitando pressupostos sobre SO, diretórios ou DNS.

### 14.2 Política de branches, revisão e sincronização

- Usar a branch padrão indicada pelo remoto. Não presumir que o histórico público está vazio e não forçar alinhamento reescrevendo commits.
- Se `main` for protegida, trabalhar em branch descritiva e abrir PR, se a autenticação/ferramentas permitirem; registrar link e status. Não contornar proteções.
- Antes de cada novo incremento, atualizar a visão do remoto com `git fetch` e integrar alterações sem descartar trabalho. Depois de cada entrega validada, `commit` e `push` imediatamente; consolidar estado local/remoto e documentar divergências.
- É permitido instalar Git/GitHub CLI e dependências necessárias no ambiente local, quando possível, sem autorizações repetidas. Se o provedor exigir login, token, MFA ou concessão inicial de acesso, a interação deve ser feita pelo proprietário sem expor segredos.
- Revisar `git diff --check`, arquivos staged, licença dos novos ativos e segredos antes de qualquer push. Usar pre-commit/CI conforme apropriado.

---

## 15. Ambiente de desenvolvimento: WSL2 e Git Bash

### 15.1 Requisitos de portabilidade

- Suportar **Windows + WSL2/Ubuntu** como ambiente preferencial, **Git Bash no Windows** como alternativa de terminal e **Linux/macOS** sempre que as dependências permitirem.
- Não assumir que executar `bash` no Git Bash equivale a Linux: diferenças de caminhos, permissões, symlinks, serviços, utilitários e integração Docker precisam ser testadas. Documentar limites reais se algum recurso exigir WSL2.
- Usar versões de runtime explicitadas (`.nvmrc`/`.node-version` ou mise; `packageManager` fixado), lockfile do `pnpm` e `.env.example` completo com valores falsos e explicações.
- Padronizar scripts `pnpm` com implementação portável em Node/TypeScript para operações que não funcionariam nativamente nos dois terminais. Evitar dependência exclusiva de `sudo`, `sed -i`, `readlink`, `cp -r`, separadores de caminho hardcoded ou scripts Bash não portáveis sem alternativa.
- Configurar `.gitattributes` para arquivos de script com LF, evitando falhas de shebang em WSL. Não exigir que projetos estejam em caminho único (`C:\`, `/mnt/c/` ou `~/src`). Recomendar filesystem do WSL para melhor I/O do desenvolvimento Linux, sem tornar isso pré-requisito.
- Priorizar `Docker Compose` para PostgreSQL/Redis/serviços locais, com volumes persistentes só de desenvolvimento, healthchecks e portas configuráveis. Quando Git Bash não tiver acesso ao Docker Desktop, fornecer diagnóstico e opção de uso via WSL; não afirmar suporte pleno sem teste.
- **Permissão permanente para instalações locais necessárias:** instalar runtimes, pacotes, CLI, bibliotecas, browsers de teste Playwright, containers e utilitários usados na solução, sem nova confirmação para cada instalação. Fixar versões, justificar dependências e documentar comandos e alterações. Respeitar exigências reais de elevação/UAC, licenças, segurança do SO e ausência de direitos de administrador; não usar meios para contornar controles.
- Não executar instalações invasivas no servidor de produção, comprar serviços, alterar firewall/rede corporativa ou armazenar credenciais sem escopo autorizado. Nunca solicitar aprovação repetitiva para operações locais ordinárias já cobertas por estas instruções.

### 15.2 Interface de comandos que o projeto deve oferecer

Criar e documentar, conforme as funcionalidades forem surgindo, scripts reais e coerentes no `package.json` da raiz:

| Comando desejado | Finalidade | Critério |
|---|---|---|
| `pnpm bootstrap` | Verifica ambiente, instala dependências e configura arquivos locais seguros | Idempotente; nunca apaga `.env` existente |
| `pnpm dev` | Inicia web, API e worker e serviços necessários | Mensagens de saúde e URLs locais claras |
| `pnpm db:migrate` | Aplica migrações locais versionadas | Nunca conecta à produção por engano |
| `pnpm db:seed` | Cria dois tenants e dados totalmente fictícios | Repetível e isolado |
| `pnpm lint` / `pnpm typecheck` | Qualidade estática e tipos | Sem erros |
| `pnpm test` | Unitários e serviços essenciais | Saída e código de erro corretos |
| `pnpm test:integration` | Banco, autorização e contratos | Cenários reais com banco de testes |
| `pnpm test:e2e` | Jornadas de usuário e mobile viewport | Executável localmente por Playwright |
| `pnpm test:tenant-isolation` | Prova de isolamento entre clínicas | Negar vazamento mesmo por busca/anexo |
| `pnpm perf:check` | Benchmark reproduzível de operações críticas | Relatório e limites de regressão |
| `pnpm docs:validate` | Existência/integridade dos guias HTML por incremento | Falha se mudança exigível não documentada |
| `pnpm verify` | Agrega lint, tipos, testes, segurança e docs | Gate obrigatório pré-push e pré-deploy |
| `pnpm build` | Compila artefatos de produção sem segredos | Compatível com estratégia de deploy |
| `pnpm smoke` | Verifica endpoints e fluxos críticos do ambiente alvo | Sem efeitos clínicos ou financeiros reais |

**Regra:** não divulgar comando inexistente como disponível. Implementá-lo e testá-lo gradualmente; documentar de forma explícita os comandos reais usados enquanto a automação não existir.

### 15.3 Diagnóstico da primeira execução

Verificar e documentar versões/saídas de `git --version`, `node --version`, `pnpm --version`, `docker --version`, `docker compose version` e status dos serviços, quando instalados. Instalar o necessário localmente se faltar. Capturar diferenças entre WSL e Git Bash no `docs/TESTING.md`, com troubleshooting para permissões, Docker Desktop, portas, paths e navegador de e2e.

---

## 16. Laboratório local e gates obrigatórios de testes

### 16.1 Regra central

**Nenhuma mudança segue para produção sem testes locais completos e evidência de sucesso.** As verificações automatizadas do GitHub devem repetir as verificações obrigatórias, mas **não substituem** a validação do desenvolvedor no ambiente local. Git push de incrementos aprovados é autorizado; publicação em produção é uma etapa distinta, controlada.

Criar ambiente `development` e `test` separado de `production`, com banco de testes e armazenamento falso/privado; nunca fazer e2e, carga ou limpeza de dados apontando para a base real. Preferir CI com containers efêmeros para testes reproduzíveis.

### 16.2 Matriz mínima, cumulativa e ampliável

1. **Qualidade de código:** lint, formatter, tipos, builds e migrações válidas; auditoria de dependências com classificação de risco.
2. **Domínio clínico:** pacientes, responsáveis, atendimentos, registros auditados, modelos médicos, anexos, assinaturas e histórico imutável conforme regras.
3. **Segurança multi-tenant:** usuários dos tenants A/B; impedir acesso, busca, listagem, exportação, download e alteração cruzada, inclusive mediante IDs previsíveis, sessões trocadas e manipulação de `tenant_id`.
4. **Busca avançada:** diagnósticos confirmados/suspeitos, termos clínicos, múltiplos filtros, resultados vazios, relevância, acentuação, paginação, PDF/DOCX extraído e isolamento dos índices.
5. **Documentos:** upload seguro, conversão, preservação do original, template por tenant, placeholders, versões, PDF gerado e acesso restrito.
6. **Interface desktop/mobile:** navegação, touch targets, contraste, formulários, teclado, erros, acessibilidade, agenda e fluxos completos com viewport mobile realista.
7. **Integração:** API, banco, filas, cache, jobs assíncronos, autenticação, expiração de sessão e recuperação de falhas.
8. **Performance:** consultas com índices e `EXPLAIN ANALYZE` quando necessário, p50/p95/p99 com cenário documentado, web vitals (LCP/INP/CLS), peso de JS e consumo de memória/CPU; estabelecer baseline antes de impor limites irreais.
9. **Resiliência e segurança:** limites de upload, arquivo malicioso de teste, timeouts, rate limits, logging sem dados sensíveis, backup/restauração simulada e smoke pós-build.
10. **Documentação e distribuição:** conteúdo obrigatório de `docs/changes/`, checkpoint atual, exemplos de variáveis de ambiente, README de setup e aderência ao modo self-hosted.

### 16.3 Isolamento do ambiente de testes

- Dados fictícios, tenants de teste e usuários próprios; nunca copiar paciente/cliente real para um ambiente local ou log público.
- Guardas no código e scripts para recusar conexão com hosts/credenciais de produção durante `test`, `seed`, `reset` e carga.
- Seeds idempotentes, fixtures versionadas, teardown seguro e bancos com nomes próprios de desenvolvimento/teste.
- Ambiente offline/degradado e compatibilidade em WSL/Git Bash devem ter procedimento de validação e erros claros.
- Em caso de falhas, corrigir antes de aprovar a entrega. Se não puder rodar um teste por limitação real de ambiente, marcar **não executado**, indicar motivo e não afirmar que o gate está verde.

### 16.4 Publicação separada de desenvolvimento

Antes de liberar a versão no servidor Locaweb: `verify` + build de produção + smoke local do artefato + backup e plano de migração/rollback + revisão de segurança + checklist documentado. O HTML da mudança orienta execução do deploy via FileZilla/SSH, **não desencadeia automaticamente produção**. Após o deploy, executar smoke não destrutivo no domínio de destino, sem dados reais inventados.

---

## 17. Documentação HTML obrigatória para CADA implementação

### 17.1 Geração em todo incremento

**Desde o primeiro código/configuração funcional**, criar ao menos um arquivo HTML em `docs/changes/` para cada incremento entregável, com nome único e ordenável, por exemplo `2026-10-15-bootstrap-monorepo.html`. Uma alteração que envolva múltiplas etapas relacionadas pode ter um guia unificado, contanto que **nenhuma mudança implementada fique sem documentação**. Atualizar o HTML quando o procedimento/teste mudar. Registrar referências mútuas entre o guia, checkpoint e commit/PR quando conhecido.

O HTML deve abrir diretamente em navegador, sem servidor externo, com **layout agradável, responsivo e legível em celular**; CSS embutido ou assets locais versionados (sem CDN obrigatória), headings semânticos, copy/paste de comandos e blocos de aviso. Evitar scripts remotos e conteúdos sensíveis. Basear todos os passos no código real; nunca gerar comandos genéricos que não funcionem.

### 17.2 Estrutura mínima de cada HTML

1. **Identificação:** título, ID da mudança, data/versão, status, módulo afetado e objetivo de negócio.
2. **Resumo do que mudou:** antes/depois, telas/rotas, arquivos relevantes, migrations, configurações e dependências instaladas.
3. **Pré-requisitos locais:** versões de Node/pnpm, Docker/WSL/Git Bash, portas, armazenamento e variáveis de ambiente **sem valores secretos**.
4. **Passo a passo da implementação:** comandos concretos, ordem de execução, onde executar, arquivos afetados e resultado esperado de cada passo; distinguir novos setups de atualização de instalação existente.
5. **Como testar:** ações manuais e automáticas, comandos executados, seed/fixtures fictícias, resultados esperados, testes de falha e isolamento entre tenants, inclusive no celular quando aplicável.
6. **Validação de performance e usabilidade:** cenários, medidas realmente coletadas, comparação com baseline se existir; se não coletado, marcar pendente e justificar.
7. **Implantação:** quando aplicável, instruções de build, migração, envio FileZilla via SFTP/FTPS e etapas que exigem SSH/VPS; indicar explicitamente se não pode ser publicado com o plano atual.
8. **Recuperação/rollback:** retorno à versão anterior, riscos de migração, backup e passos para reverter sem perda de dados.
9. **Troubleshooting:** mensagens de erro comuns, como conferir healthchecks/logs, riscos e limitações.
10. **Evidências e status:** testes `PASS/FAIL/NOT RUN`, referência a issues/commit após sincronização (sem introduzir ciclo de commits para preencher hash) e próximos passos.

### 17.3 Template e fiscalização

- Manter um `docs/changes/TEMPLATE.html` com os 10 itens, comentários `TODO` legíveis e estilos básicos embutidos. Cada implementação deve gerar um **novo HTML preenchido**, e não reutilizar/reescrever o arquivo da entrega anterior.
- Criar `pnpm docs:validate` para verificar pelo menos: arquivos HTML bem formados, seções mandatórias, link no `docs/PROGRESS.md` e um guia associado a todo incremento de implementação. Integrar esta verificação ao `pnpm verify` e à CI.
- Não anexar prints com identificação real de tutores/pacientes nem incluir segredos em exemplos de comandos. Documentos de demonstração devem usar dados 100% fictícios.
- O guia HTML é **documentação de engenharia**: não deve ser confundido com template de prontuário ou anexo clínico de tenant.

---

## 18. Evolução permanente, performance e qualidade de experiência

- A cada implementação questionar: o fluxo ficou mais rápido? Há clique/campo desnecessário? O mobile fica confortável em rede lenta? A busca clínica retorna resultados relevantes sem expor outra clínica? A migração é reversível? O bundle cresceu?
- Buscar menor tempo de conclusão das tarefas reais: agendar consulta, localizar tutor/pet, pesquisar diagnóstico e localizar frase de prontuário, consultar histórico, gerar receita e registrar atendimento.
- Priorizar índices adequados, consultas parametrizadas e paginadas, N+1 sob controle, virtualização de listas longas, cache somente quando seguro e invalidado por tenant, operações assíncronas para PDF/importação e carregamento progressivo.
- Manter budgets **medidos** e não arbitrários: registrar baseline e ambiente; alertar regressão de p95 de consultas, tamanho de bundle, uso de CPU/RAM, tempo de resposta da pesquisa e métricas de UX.
- Testar fluxos críticos com dados de volume sintético representativos, sobretudo pesquisa por texto livre/filtros clínicos, agenda com vários profissionais e documentos pesados. Não usar produção para gerar carga.
- Antes de introduzir ferramenta, serviço ou dependência adicional, considerar custo de manutenção, hospedagem Locaweb, memória e compatibilidade com deploy reproduzível em múltiplos ambientes.
- Manter `docs/IDEAS.md` com sugestões próprias organizadas por impacto, urgência, custo e dependências. O Claude Code pode propor melhorias e executá-las incrementalmente quando não alterarem premissas legais/comerciais/irreversíveis; priorizar pela jornada clínica e segurança.
- **Nunca sacrificar isolamento de dados, integridade do prontuário ou rastreabilidade por performance**. Benchmarks e otimizações só contam como entregues após testes de regressão.

### 18.1 Critério operacional de conclusão de cada ciclo

O ciclo é encerrado com: **implementação real → testes locais → performance/usabilidade verificadas → HTML de aplicação/testes → atualização do checkpoint → revisão de segredos → commit → push → confirmação de sincronização → próximo passo documentado**. Se qualquer etapa falhar, registrar explicitamente onde parou, como reproduzir e o que falta. Se houver limite de contexto, priorizar deixar um checkpoint utilizável e trabalho local preservado, sem declarar conclusão falsa.
