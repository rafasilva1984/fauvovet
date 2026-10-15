#!/usr/bin/env node
// Gera um token de deploy aleatório. O TOKEN fica só com você (gerenciador de senhas);
// no .env do servidor vai apenas o SHA-256 (DEPLOY_TOKEN_SHA256). Nada é gravado em arquivo.
import { createHash, randomBytes } from 'node:crypto';

const token = randomBytes(32).toString('base64url');
const sha = createHash('sha256').update(token).digest('hex');
console.log('TOKEN (guarde no gerenciador de senhas; não cole em arquivos do projeto):');
console.log(`  ${token}`);
console.log('\nLinha para o .env do SERVIDOR:');
console.log(`  DEPLOY_TOKEN_SHA256=${sha}`);
