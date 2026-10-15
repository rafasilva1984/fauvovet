import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import type { NestExpressApplication } from '@nestjs/platform-express';
import cookieParser from 'cookie-parser';
import helmet from 'helmet';
import { AppModule } from './app.module';
import { requestContextMiddleware } from './common/request-context';
import type { Env } from './config/env';

export function apiPrefix(config: Env): string {
  return `${config.BASE_PATH}/api/v1`.replace(/^\//, '');
}

export async function createApp(config: Env): Promise<NestExpressApplication> {
  const app = await NestFactory.create<NestExpressApplication>(AppModule.forRoot(config), {
    logger: config.NODE_ENV === 'test' ? false : ['error', 'warn', 'log'],
    bodyParser: false,
  });
  app.set('trust proxy', config.TRUST_PROXY ? 1 : false);
  app.disable('x-powered-by');
  app.use(requestContextMiddleware);
  app.use(
    helmet({
      contentSecurityPolicy: { directives: { defaultSrc: ["'none'"], frameAncestors: ["'none'"] } },
    }),
  );
  app.useBodyParser('json', { limit: '100kb' });
  app.use(cookieParser());
  app.setGlobalPrefix(apiPrefix(config));
  app.enableShutdownHooks();
  return app;
}
