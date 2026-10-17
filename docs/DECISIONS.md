# Registro de decisões (ADR) — FauvoVet

> **Atenção:** a ADR-010 (15/10/2026) trocou a stack para PHP + MySQL por causa da hospedagem contratada.
> As ADRs 002, 003, 005 e 009 ficam registradas como histórico e estão **substituídas** (ver ADR-010).

Formato: contexto → decisão → consequências (benefício, custo, risco, compatibilidade Locaweb). Decisões são
reversíveis salvo indicação.

## ADR-001 — Monorepo pnpm com TypeScript estrito (2026-10-15) — **SUBSTITUÍDA pela ADR-010** (Node/pnpm agora só para build e testes)

- **Decisão:** pnpm workspaces (`apps/*`, `packages/*`), TypeScript 6.0.3 fixado, Node 24 (`mise.toml`, `.nvmrc`),
  `packageManager: pnpm@12.4.2`.
- **Por quê TS 6 e não 7:** `typescript-eslint` 8.71 suporta `<6.1`; o TS 7 (port nativo) ainda não é compatível com o
  ecossistema de lint. Reavaliar quando houver suporte.
- **Consequência:** sem Turborepo/Nx por ora (menos dependências); `pnpm -r` basta no tamanho atual.

## ADR-002 — NestJS 12 + SQL explícito com `pg` (sem ORM nesta fase) (2026-10-15) — **SUBSTITUÍDA pela ADR-010**

- **Contexto:** o CLAUDE.md sugere Prisma ou Drizzle "após avaliar RLS e migrações".
- **Decisão:** migrações em SQL puro versionadas com checksum (`apps/api/migrations`, runner próprio de ~60 linhas) e
  consultas parametrizadas com `pg`. O contexto RLS por transação (`set_config(..., true)`) fica explícito em
  `DbService.withContext`.
- **Benefícios:** controle total de políticas RLS, FKs compostas, funções `SECURITY DEFINER` e índices de busca
  (`tsvector`, `pg_trgm`) que ORMs abstraem mal; zero mágica no caminho crítico de isolamento.
- **Custos/risco:** menos tipagem automática de linhas. Mitigação: DTOs em `packages/contracts` e testes de integração
  contra banco real. Reavaliar Drizzle (query builder tipado compatível com SQL manual) na Fase 1.

## ADR-003 — Multi-tenant em banco compartilhado com RLS (2026-10-15) — **SUBSTITUÍDA pela ADR-010**

- **Decisão:** coluna `tenant_id` + `ENABLE ROW LEVEL SECURITY` com políticas `USING` e `WITH CHECK` baseadas em
  `app.current_tenant_id()`; papel da aplicação sem posse das tabelas e sem `BYPASSRLS`; papel de migração separado.
- **Por que `ENABLE` e não `FORCE`:** o dono (papel de migração) precisa executar funções `SECURITY DEFINER` estreitas
  (login, sessão, vínculos próprios) e seeds. A aplicação nunca conecta como dono — verificado por teste
  (`papel da aplicação não é superuser, não tem BYPASSRLS e não é dono`). Tabelas novas devem ter RLS — também
  verificado por teste (`todas as tabelas ... têm RLS habilitada`).
- **Identidade global:** `users` e `user_sessions` não têm `tenant_id` (uma pessoa pode atuar em várias clínicas), mas
  têm RLS: o usuário vê a si mesmo e os membros do tenant ativo; sessões só do próprio usuário.
- **Futuro:** tenants grandes podem migrar para banco dedicado mantendo a API (o contexto continua por sessão).

## ADR-004 — Sessão opaca em cookie HttpOnly + CSRF por token de sessão (2026-10-15) — princípios mantidos; implementação atual na ADR-010

- **Decisão:** token aleatório de 256 bits no cookie `fv_session` (`HttpOnly`, `SameSite=Lax`, `Secure` em produção,
  `Path=BASE_PATH`); apenas o SHA-256 é guardado. Mutações exigem `X-CSRF-Token` (token por sessão, comparação em tempo
  constante) e, se houver `Origin`, ele deve ser igual a `PUBLIC_ORIGIN`.
- **Por que não JWT:** revogação imediata (logout, bloqueio) e menor superfície.
- **Senhas:** Argon2id (`@node-rs/argon2`, binário pré-compilado, m=19 MiB, t=2, p=1 — parâmetros OWASP).
  Bloqueio após `LOGIN_MAX_FAILURES` (padrão 10) por `LOGIN_LOCK_MINUTES` (15). Mensagem idêntica para e-mail
  inexistente e senha errada, com verificação de hash fictício para equalizar tempo.
- **Pendências:** MFA para perfis privilegiados, recuperação de senha por e-mail e gestão de dispositivos (Fase 1/5).

## ADR-005 — PostgreSQL local sem root/Docker via binários `embedded-postgres` (2026-10-15) — **SUBSTITUÍDA pela ADR-010**

- **Contexto:** a máquina de desenvolvimento (WSL2 Ubuntu 26.04) não tem `sudo` sem senha e o Docker Desktop não está
  integrado à distro. O CLAUDE.md prioriza Docker Compose.
- **Decisão:** `scripts/db-local.mjs` usa os binários PostgreSQL 18 do pacote npm `embedded-postgres`
  (`initdb`/`pg_ctl`) com dados em `.data/pg`, escutando só em `127.0.0.1:54329`, autenticação SCRAM. A CI usa o mesmo
  caminho. Para quem já tem PostgreSQL (Docker ou nativo): `pnpm bootstrap --external-db`.
- **Docker Compose:** adiado até haver ambiente com Docker para **testar** (não publicar configuração não verificada).
  Necessário de qualquer forma para o deploy em VPS (Fase 0/1).
- **Risco:** pacote em versão `beta` do empacotamento (o PostgreSQL em si é 18.4 estável). Uso restrito a dev/teste.

## ADR-006 — Licença MIT (2026-10-15)

- Aplicada a recomendação inicial do CLAUDE.md. **Reversível pelo proprietário** antes de contribuições externas
  relevantes. Verificar licenças de dependências e ativos visuais antes de redistribuir.

## ADR-007 — Cadastro público (onboarding) desligado por padrão (2026-10-15)

- `ALLOW_SELF_SIGNUP=false` por padrão no código; `true` no `.env.example` de desenvolvimento. Liberar em produção é
  decisão comercial (trial, verificação de e-mail, anti-abuso). Conflito de e-mail retorna mensagem genérica.

## ADR-008 — Documento (CPF/CNPJ) não é único globalmente (2026-10-15)

- Unicidade global permitiria descobrir se um CPF/CNPJ já é cliente do FauvoVet (enumeração entre tenants). Validamos
  apenas dígitos verificadores. Deduplicação comercial fica para o painel Superadmin auditado.

## ADR-009 — Web: Next.js 16 App Router, sessão no cliente, tokens CSS próprios (2026-10-15) — **SUBSTITUÍDA pela ADR-010**

- **Decisão:** `apps/web` com Next.js 16.4 + React 19 + Tailwind CSS 4. Páginas autenticadas são _client components_
  que consultam `GET /auth/me` e `/tenant`; a autorização real permanece na API (a UI apenas esconde o que o papel não
  pode). `basePath` vem de `BASE_PATH` em tempo de build; `/api/*` é reescrito para `API_INTERNAL_URL` (também fixado
  no build). Em produção o proxy reverso deve rotear `/fauvovet/api/` direto à API.
- **Design system:** tokens CSS (`--fv-*`) mapeados no `@theme` do Tailwind; o tenant só altera primária/secundária
  (com cor de texto calculada para contraste AA) e modo claro/escuro/automático. Componentes próprios acessíveis
  (`components/ui.tsx`) e ícones SVG próprios — **shadcn/Radix adiados** até haver componentes complexos (diálogos,
  menus, combobox) que justifiquem a dependência.
- **Tipografia:** pilha de fontes do sistema (sem download de fontes externas: privacidade, offline, performance).
- **CSP** na web: `script-src 'self' 'unsafe-inline'` (necessário para a hidratação do Next sem nonce). Evoluir para
  CSP com nonce via `proxy.ts` quando houver renderização dinâmica — registrado em `docs/IDEAS.md`.
- **Custo/risco:** checagem de sessão no cliente causa um _skeleton_ breve na primeira carga; sem SSR de dados sensíveis
  (bom para cache/CDN, nada clínico em HTML estático).

## ADR-010 — Stack PHP 8 + Laravel 13 + MySQL 5.7+, isolamento multi-tenant em camadas no código (2026-10-15)

**Contexto.** O proprietário informou o plano real: **Hospedagem Dedicada Locaweb 4 GB**, só painel, **sem SSH**,
banco **MySQL** do painel, domínio já apontado. Pela documentação pública da Locaweb, a hospedagem de sites Linux
suporta **PHP** e MySQL; Node.js persistente não é oferecido. A stack anterior (NestJS + Next.js + PostgreSQL com RLS)
não roda nesse plano. O proprietário pediu reescrita sem PostgreSQL, com foco em segurança e multi-tenant, seguindo o
modelo de outro projeto dele já operado na Locaweb (Laravel + Vite, Node só para build local, deploy por FTP).

**Decisão.**

- Backend **Laravel 13** (PHP ≥ 8.3; `config.platform.php = 8.3.0` para o `vendor/` funcionar em 8.3–8.5),
  Blade + **Alpine (build CSP)** + Tailwind 4 compilados localmente pelo Vite; só `public/build` vai ao servidor.
- **MySQL/Percona 5.7+** (sem recursos exclusivos do 8.0). Local e CI usam Percona 5.7.44 sem root
  (`scripts/local-mysql.mjs`, checksum SHA-256 fixado).
- **Isolamento sem RLS, em camadas** (detalhes em `docs/SECURITY.md`):
  1. clínica ativa vem **só da sessão no servidor** e é revalidada a cada requisição (`ResolveTenant`);
  2. escopo obrigatório que **falha fechado** (`BelongsToTenant` + `TenantScope`): sem clínica ativa, a consulta não executa;
  3. **firewall de SQL** (`TenantQueryFirewall`, antes de cada consulta): bloqueia SQL em tabela de clínica sem
     `tenant_id`, ou cujo valor não seja o da clínica ativa;
  4. **FKs compostas** `(tenant_id, x_id)` no banco impedem relacionar dados de clínicas diferentes;
  5. testes de arquitetura: toda tabela com `tenant_id` precisa estar no firewall; todo model de clínica implementa `TenantOwned`.
- Deploy **sem SSH**: `pnpm deploy:package` monta `app-fauvovet/` (fora da web) e `public_html/fauvovet/`; migrações e
  caches por endpoints HTTP protegidos (`/_deploy/*`: flag + token SHA-256 + limite em arquivo).
- Sessão no banco, cookie criptografado `HttpOnly`/`SameSite=Lax`/`Path=/fauvovet` (`Secure` com HTTPS), CSRF do
  Laravel (token + origem) sem cookie `XSRF-TOKEN`, CSP com nonce sem `unsafe-inline`/`unsafe-eval`.

**Consequências.**

- **Mais fraco que RLS** num ponto: o banco não sabe qual é a clínica ativa; uma consulta escrita com `withoutGlobalScopes`
  ou SQL bruto é barrada pelo firewall (heurístico), não pelo motor do banco. Mitigação: firewall + testes que tentam
  burlá-lo + revisão. Registrado como risco aceito em `docs/SECURITY.md`.
- Auditoria "somente inclusão" é garantida pela aplicação (model bloqueia UPDATE/DELETE); a hospedagem compartilhada
  normalmente não permite trigger/usuários separados no MySQL.
- A busca clínica (Fase 2) será feita com `FULLTEXT` do InnoDB + tabelas de índice próprias por clínica; avaliar
  qualidade em português (stopwords, acentos) antes de prometer paridade com `tsvector` (ver `docs/IDEAS.md`).
- O código Node/NestJS/Next anterior permanece no histórico do Git (commits `7d303af`, `4a9ade7`).
- **Reversível:** se a hospedagem mudar para VPS, a aplicação Laravel roda igual; o isolamento em camadas continua válido.

## ADR-011 — Agenda em UTC com fuso por unidade; migrações MySQL não são transacionais (2026-10-16)

- **Decisão:** horários da agenda gravados em `DATETIME` UTC; a tela converte do/para o fuso da **unidade**
  (`units.timezone`). Conflito de horário é por **vínculo** de profissional (a mesma pessoa em duas clínicas tem dois
  vínculos e agendas independentes). Encaixe (`is_walk_in`) permite sobreposição consciente.
- **Por quê:** unidades em estados diferentes (ex.: Manaus UTC−4) e atendimento domiciliar; UTC evita ambiguidade.
  Conflito por vínculo preserva o isolamento: a agenda de uma clínica nunca revela ocupação em outra.
- **Risco registrado:** no MySQL, DDL não roda em transação — uma migração que falhe no meio deixa tabelas parciais.
  Achado real nesta entrega (nome de índice > 64 caracteres falhou no ensaio local). Mitigação: toda migração roda
  em `pnpm verify` (banco `_test` do zero) antes do pacote; nomes de índice explícitos e curtos; backup obrigatório
  antes de `migrar` no servidor.

## ADR-012 — Linguagem visual 2.0: fonte própria, menu escuro, degradês da clínica (2026-10-16)

- **Decisão:** fonte **Plus Jakarta Sans** variável (OFL-1.1) auto-hospedada no build (sem CDN, CSP `font-src
'self'`); menu lateral escuro fixo com destaque em degradê da cor da clínica; superfícies com malha suave
  (`fv-mesh`), vidro (`fv-glass`), cantos 20–28 px, sombras em camadas, avatares com 8 tons AA, animações curtas
  desligadas por `prefers-reduced-motion`.
- **Personalização preservada:** degradês, realces e brilhos derivam de `--fv-primary`/`--fv-secondary` via
  `color-mix` — nenhum CSS por cliente. Cores dinâmicas nunca usam atributo `style` (CSP): classes fixas
  (`fv-tone-*`) ou `<style nonce>`.
- **Build:** `base: './'` no Vite para que as URLs de fontes dentro do CSS sejam relativas e funcionem em
  `/fauvovet` (antes apontavam para `/build` na raiz do domínio).
- **Custo medido:** ~98 → 149 KB transferidos na primeira visita (fonte em cache depois); LCP de laboratório ≤ 120
  ms; p95 do painel 48 → 54 ms. Acessibilidade AA mantida (axe medido no estado final, sem animação).

## ADR-013 — Prontuário: assinar trava o registro; correções só por adendo; acesso auditado (2026-10-17)

- **Decisão:** atendimento nasce rascunho (equipe clínica edita); **finalizar e assinar** exige o papel
  médico-veterinário (`clinical:sign`) e uma confirmação explícita. Depois disso o registro é imutável — a regra vale
  na tela, no controller e no **model** (até em modo sistema); correções entram como **adendos** somente-inclusão,
  com data e autor (CLAUDE.md §2.1 D, §12.5).
- **Acesso:** ler prontuário é auditado (`encounter.viewed`, `patient.clinical_viewed`); a auditoria de alterações
  guarda só os nomes dos campos, nunca o conteúdo clínico (§8.1). Recepção e financeiro não veem nada clínico.
- **Assinatura:** é a assinatura **eletrônica simples** do sistema (autor autenticado + data + bloqueio). Não é
  assinatura digital ICP-Brasil (§4.4) — isso fica para a integração com provedor especializado.
- **CEP:** consulta pelo servidor (ViaCEP) em vez do navegador, para manter a CSP sem conexões externas; só o CEP
  sai do sistema; falha não impede o cadastro.

## ADR-014 — Documentos em PDF com dompdf, arquivo privado e preservado (2026-10-17)

- **Decisão:** gerar PDFs no servidor com **dompdf 3.1** (PHP puro, roda na Locaweb sem binários; licença LGPL-2.1,
  usado sem modificação — compatível com o projeto MIT). Recursos remotos, PHP e JavaScript do dompdf desligados;
  `chroot` em `resources/pdf`. Fonte DejaVu (acentos).
- **Preservação:** o documento guarda o texto final e o PDF em disco privado (`storage/app/private`), com SHA-256
  conferido a cada download; editar o modelo cria nova versão e não altera documentos emitidos; documento não é
  apagado nem reescrito — é anulado com motivo.
- **Assinatura:** eletrônica simples (profissional autenticado + CRMV + data + código de verificação). Assinatura
  digital ICP-Brasil e receita de controle especial ficam para provedor especializado e validação jurídica (§4.4).
- **Custo medido:** ~58 ms por PDF (local), ~23 KB por receita.

## ADR-015 — Casos científicos: referência ao prontuário, pseudônimo e consentimento (2026-10-18)

- **Contexto:** o proprietário pediu que o sistema tenha "um viés para criar, ao longo do tempo, casos científicos"
  com os casos já tratados. Isso envolve uso secundário de dados clínicos e pessoais (LGPD; ética em pesquisa).
- **Decisão:**
  - **Série de casos** nasce da busca avançada: os critérios ficam gravados, para reproduzir a seleção. **Relato de
    caso** nasce de um atendimento finalizado.
  - O caso **referencia** o prontuário (paciente + atendimento de referência) e **não copia** texto clínico. O texto
    científico (pergunta, resumo, desfecho, observações) é escrito pela equipe.
  - Na **exportação** (CSV/PDF), cada paciente vira "Caso NN", as datas viram mês/ano e a idade é a da referência.
    Nunca saem nome, responsável, documento, microchip, IDs ou texto livre do prontuário.
  - Os textos da equipe passam por **redação automática**: e-mails, sequências longas de números e nomes conhecidos
    de pacientes e responsáveis do caso são omitidos. É uma rede de segurança, não substitui a revisão humana.
  - O consentimento do tutor é registrado **por caso**: só "Autorizado" entra na exportação, e o rodapé informa
    quantos casos ficaram de fora.
  - Permissão `research:manage` (veterinário e titular). Criação, visualização, consentimento e exportação são
    auditados.
- **Riscos e limites:**
  - A anonimização reduz o risco de reidentificação, mas não o elimina: raça rara, espécie exótica ou série pequena
    podem identificar o animal.
  - O rodapé do PDF pede revisão e aprovação ética (ex.: CEUA, quando aplicável) antes de publicar.
  - O termo de consentimento propriamente dito (texto, assinatura) fica para um modelo de documento da clínica;
    aqui só se registra a situação e a data.

## ADR-016 — Dados de demonstração na clínica, atrás de chave e com remoção total (2026-10-18)

- **Contexto:** o proprietário pediu "um script que coloque casos no perfil atual em produção" para ver vacinação,
  busca avançada e casos científicos com conteúdo. A hospedagem não tem SSH (ADR-010): seeder/artisan não rodam lá, e
  o seed de desenvolvimento recusa produção de propósito.
- **Decisão:**
  - Tela **Configurações → Dados de demonstração**, só para o titular (`tenant:update`) e só com
    `FAUVOVET_DEMO_DATA=true` no `.env` do servidor; desligada, as rotas respondem 404.
  - Dados 100% fictícios (tutores com "(demo)", e-mails `.exemplo.test`, telefones `11 90000-01xx`), na clínica
    **ativa**; autoria e assinatura dos atendimentos ficam com o titular que carregou; auditoria
    `demo_data.loaded`/`demo_data.removed`.
  - Todo registro demo tem ID `00DEM0` + CRC32 da clínica + tipo + número: ULID válido (data de 1970, que o sistema
    nunca gera) e sem colisão entre clínicas. Assim a remoção é cirúrgica.
  - **Remoção** apaga pacientes/tutores/casos demo e tudo ligado aos pacientes demo, inclusive o que a equipe
    registrou depois (atendimentos, vacinas, documentos, anexos e arquivos). É a única exceção à regra "prontuário não
    é apagado", e vale só para pacientes demo. Paciente real vinculado a tutor demo perde só o vínculo.
- **Riscos e limites:** dados demo convivem com dados reais se a chave ficar ligada. O guia manda remover a
  demonstração e desligar a chave antes do primeiro paciente real; recomendado reavaliar quando houver HTTPS e uso
  clínico real.

## ADR-017 — Busca clínica em texto livre com tabela de termos normalizados (2026-10-19)

- **Contexto:** §3 pede busca em todo o prontuário, sem acento/plural, com frases, operadores e trecho de origem. No
  MySQL (ADR-010) não há `tsvector`/`unaccent`. O FULLTEXT do InnoDB tem limites: ignora palavras com menos de 3
  letras, usa stopwords em inglês (`com`, `de`…), não enxerga linhas ainda não confirmadas (quebra os testes em
  transação) e não pode ser configurado na hospedagem compartilhada.
- **Decisão:**
  - Índice próprio derivado do prontuário. Cada trecho (campo de atendimento finalizado, diagnóstico, adendo, alerta,
    documento, vacina) tem o texto original e o normalizado; uma tabela de termos distintos fica com PK
    `(tenant_id, term, document_id)`, e a busca por prefixo usa B-tree.
  - **Normalização em PHP**, sem `intl`: minúsculas e sem acento, preservando a posição de cada caractere (permite
    destacar o original); plural do português reduzido igual no índice e na consulta; stopwords em português.
  - **Semântica por paciente:** termos de uma alternativa podem estar em registros diferentes; `OU` separa
    alternativas; `NÃO`/`-` exclui pacientes. Frases são pré-filtradas com `LIKE` e confirmadas por regex.
  - Índice atualizado na mesma transação das gravações clínicas. Rascunho não entra. A versão do índice força a
    reconstrução automática quando a regra mudar, e o titular pode reconstruir manualmente.
  - Mesmo controle de acesso do prontuário (`clinical:read`); auditoria sem o termo; rate limit; teto por termo.
- **Alternativa futura:** se o volume exigir (§3.4), trocar o mecanismo por trás de `ClinicalSearchService` (ex.:
  OpenSearch) sem mudar a interface.
- **Limites:** sem sinônimos configuráveis e sem texto de anexos (próximos incrementos); a relevância é heurística
  (peso por origem × termos casados).

## ADR-018 — Texto de anexos na busca, campo único de busca e demonstração versionada (2026-10-19)

- **Contexto:** o proprietário relatou que exemplos do guia não funcionaram na produção (`xampu`, “recusa alimentar”
  OU “não se alimenta”, filtro de diagnóstico). Análise:
  - a demonstração tinha sido carregada antes da versão que trouxe os textos de exemplo, e nada avisava isso;
  - a busca principal de Pacientes não olhava o prontuário;
  - o filtro de diagnóstico exigia a frase exata;
  - aspas curvas e "ou" minúsculo não eram operadores.
    Na mesma publicação entra o próximo passo da Fase 2, texto de anexos (§3.3 ex. 4 e 10).
- **Decisão:**
  - **Campo único** (§3.2): para quem tem `clinical:read`, a busca de Pacientes (e a do topo) também roda a busca
    clínica e mostra "No prontuário" com trecho. A auditoria registra a busca sem o termo.
  - Parser aceita aspas tipográficas e `ou` minúsculo. `ou` não tem valor como palavra de busca; `NÃO` continua só em
    maiúsculas, porque "não" é conteúdo clínico.
  - Filtro de diagnóstico palavra por palavra, com a mesma normalização da busca (`SearchText::terms`).
  - **Demonstração versionada** (`DemoDataService::VERSION`, gravada no 1º responsável demo): a tela e a Busca clínica
    avisam quando ela está desatualizada, e "Atualizar demonstração" remove e carrega de novo. Subir a versão a cada
    entrega que amplia os dados.
  - **Texto de anexos:**
    - extração no envio. PDF via `smalot/pdfparser` 2.13: PHP puro, cerca de 500 KB, LGPL-3.0 usada sem modificação
      (compatível com distribuição MIT do app), requer zlib e iconv.
    - DOCX por leitor de ZIP próprio, para não depender da extensão `zip` da hospedagem.
    - Limite de 10 MB, teto de 200 mil caracteres e proteção contra zip bomb.
    - O texto fica em colunas próprias do anexo; o original não muda.
    - A situação de leitura é explícita: `indexed`, `no_text`, `failed`, `too_large`. Imagem e PDF escaneado aparecem
      como "sem texto pesquisável" (sem OCR nesta versão).
    - Anexos antigos são lidos em lotes de 30 (1ª abertura da busca ou botão).
- **Riscos:** PDFs com codificação exótica podem sair sem texto ("failed"/"no_text", visível ao usuário); leitura
  síncrona no envio pode pesar com PDFs grandes — limite de 10 MB e ideia de fila registrada em `docs/IDEAS.md`.
