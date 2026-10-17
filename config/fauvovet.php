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
    // Anexos do prontuário: tamanho máximo por arquivo (KB). O PHP do servidor também precisa aceitar
    // (upload_max_filesize/post_max_size — ver deploy/public/.user.ini e o diagnóstico de deploy).
    'attachments' => [
        'max_kb' => (int) env('FAUVOVET_ATTACHMENT_MAX_KB', 15360),
    ],
    // Tela "Dados de demonstração" (titular carrega/remove pacientes fictícios na própria clínica). Padrão: desligada.
    'demo_data' => (bool) env('FAUVOVET_DEMO_DATA', false),
    // Ferramentas de deploy sem SSH (migração/diagnóstico via HTTP). Ver docs/DEPLOY_LOCAWEB.md.
    'deploy' => [
        'enabled' => (bool) env('DEPLOY_TOOLS_ENABLED', false),
        'token_sha256' => (string) env('DEPLOY_TOKEN_SHA256', ''),
    ],
];
