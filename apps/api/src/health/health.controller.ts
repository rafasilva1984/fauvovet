import { Controller, Get, HttpException } from '@nestjs/common';
import { DbService } from '../db/db.service';
import { Public } from '../common/decorators';

@Controller('health')
export class HealthController {
  constructor(private readonly db: DbService) {}

  @Public()
  @Get()
  async health() {
    const started = Date.now();
    try {
      await this.db.ping();
    } catch {
      throw new HttpException({ status: 'degraded', database: 'down' }, 503);
    }
    return {
      status: 'ok',
      database: 'up',
      dbLatencyMs: Date.now() - started,
      version: process.env.npm_package_version ?? '0.1.0',
    };
  }
}
