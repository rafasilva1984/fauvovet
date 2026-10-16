import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Build feito localmente; só public/build vai para o servidor (a hospedagem não executa Node).
// Fonte auto-hospedada (Plus Jakarta Sans, OFL) servida pelo próprio build: nenhuma fonte externa.
export default defineConfig({
  // base relativa: URLs dentro do CSS (fontes) ficam relativas ao próprio arquivo, então funcionam
  // em qualquer subdiretório (/fauvovet) sem depender do domínio. O @vite do Laravel usa o manifesto.
  base: './',
  plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: false }), tailwindcss()],
  build: { sourcemap: false },
});
