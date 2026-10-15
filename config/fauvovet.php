<?php

return [
    // Cadastro público de clínicas. Padrão seguro: desligado.
    'allow_self_signup' => (bool) env('FAUVOVET_ALLOW_SELF_SIGNUP', false),
    'login' => [
        'max_failures' => (int) env('FAUVOVET_LOGIN_MAX_FAILURES', 10),
        'lock_minutes' => (int) env('FAUVOVET_LOGIN_LOCK_MINUTES', 15),
    ],
    // Senha dos usuários FICTÍCIOS do seed (somente desenvolvimento/testes).
    'seed_password' => (string) env('SEED_PASSWORD', 'fauvovet-demo-2026'),
    // Ferramentas de deploy sem SSH (migração/diagnóstico via HTTP). Ver docs/DEPLOY_LOCAWEB.md.
    'deploy' => [
        'enabled' => (bool) env('DEPLOY_TOOLS_ENABLED', false),
        'token_sha256' => (string) env('DEPLOY_TOKEN_SHA256', ''),
    ],
];
