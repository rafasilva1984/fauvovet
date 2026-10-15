# Contribuindo com o FauvoVet

1. Faça fork/branch descritiva (`feat/agenda-semana`, `fix/csrf-origin`).
2. `pnpm bootstrap` e desenvolva com `pnpm dev`.
3. Cada incremento deve trazer: testes (inclusive **isolamento entre dois tenants** quando tocar dados), migração SQL
   nova (nunca editar uma migração já aplicada — o checksum impede), guia HTML em `docs/changes/AAAA-MM-DD-slug.html`
   a partir do `TEMPLATE.html`, e atualização de `docs/PROGRESS.md`.
4. Rode `pnpm verify` antes do push. Ele precisa terminar com todas as etapas `PASS`.
5. Abra PR descrevendo o que funciona, o que foi testado, pendências e riscos.

## Convenções

- TypeScript estrito; validação de entrada com `zod` em `packages/contracts`.
- SQL sempre parametrizado; toda tabela de domínio com `tenant_id`, RLS (`USING` + `WITH CHECK`) e, quando houver
  relação entre tabelas de tenant, FK composta `(tenant_id, id)`.
- Permissões no backend com `@RequirePermission()`; a interface apenas reflete.
- Mensagens ao usuário em pt-BR; código e identificadores em inglês.
- Dados de teste **100% fictícios** (domínios `.test`). Nunca usar dados reais de clínicas, tutores ou pacientes.
- Scripts em Node (`scripts/*.mjs`) para funcionar em WSL, Git Bash, Linux e macOS; evite `sed -i`, `cp -r`, `sudo`.
- Finais de linha LF (`.gitattributes`).

## Segurança

Não abra issue pública para vulnerabilidades — use _security advisory_ privada do GitHub (veja `docs/SECURITY.md`).
