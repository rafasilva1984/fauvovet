import swc from 'unplugin-swc';
import { defineConfig } from 'vitest/config';

// SWC preserva metadados de decorators exigidos pela injeção de dependências do NestJS.
const plugins = [swc.vite({ module: { type: 'es6' } })];

export default defineConfig({
  test: {
    projects: [
      { plugins, test: { name: 'unit', include: ['src/**/*.test.ts'], environment: 'node' } },
      {
        plugins,
        test: {
          name: 'integration',
          include: ['test/**/*.test.ts'],
          environment: 'node',
          globalSetup: ['test/global-setup.ts'],
          fileParallelism: false,
          testTimeout: 20_000,
          hookTimeout: 30_000,
        },
      },
    ],
  },
});
