import { createApp, apiPrefix } from './app.factory';
import { loadConfig } from './config/env';

async function main() {
  const config = loadConfig();
  const app = await createApp(config);
  await app.listen(config.API_PORT, config.API_HOST);
  console.log(
    JSON.stringify({
      level: 'info',
      msg: 'api_started',
      url: `http://${config.API_HOST}:${config.API_PORT}/${apiPrefix(config)}/health`,
    }),
  );
}

main().catch((err) => {
  console.error(`Falha ao iniciar API: ${err.message}`);
  process.exit(1);
});
