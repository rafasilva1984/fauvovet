import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Build feito localmente; só public/build vai para o servidor (a hospedagem não executa Node).
// Fontes do sistema: nenhuma fonte externa (privacidade, offline, performance).
export default defineConfig({
  plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: false }), tailwindcss()],
  build: { sourcemap: false },
});
