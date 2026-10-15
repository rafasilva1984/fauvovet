import type { NextConfig } from 'next';

// basePath é definido em tempo de build (ex.: /fauvovet). Vazio = raiz do domínio.
const basePath = process.env.BASE_PATH ?? '/fauvovet';
const apiInternal = process.env.API_INTERNAL_URL ?? 'http://127.0.0.1:4000';
const isDev = process.env.NODE_ENV !== 'production';

const csp = [
  "default-src 'self'",
  // Next injeta scripts inline de hidratação; 'unsafe-eval' apenas no dev (React Refresh).
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ''}`,
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob:",
  "font-src 'self'",
  "connect-src 'self'",
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "form-action 'self'",
].join('; ');

const nextConfig: NextConfig = {
  basePath,
  output: 'standalone',
  // Diretório de build alternativo (ex.: E2E usa .next-e2e para não sobrescrever o build normal).
  distDir: process.env.NEXT_DIST_DIR ?? '.next',
  reactStrictMode: true,
  poweredByHeader: false,
  env: { NEXT_PUBLIC_BASE_PATH: basePath },
  // Em desenvolvimento a web encaminha /fauvovet/api/* para a API. Em produção o proxy reverso
  // (nginx) deve rotear /fauvovet/api/ direto para a API; esta regra fica como fallback.
  async rewrites() {
    return [{ source: '/api/:path*', destination: `${apiInternal}${basePath}/api/:path*` }];
  },
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          { key: 'Content-Security-Policy', value: csp },
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'X-Frame-Options', value: 'DENY' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
        ],
      },
    ];
  },
};

export default nextConfig;
