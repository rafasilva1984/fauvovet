import { DynamicModule, Module } from '@nestjs/common';
import { APP_FILTER, APP_GUARD } from '@nestjs/core';
import { AuditService } from './audit/audit.service';
import { AuthController, LOGIN_LIMITER } from './auth/auth.controller';
import { AuthService } from './auth/auth.service';
import { SessionGuard } from './auth/session.guard';
import { AllExceptionsFilter } from './common/errors';
import { RateLimiter } from './common/rate-limit';
import { CONFIG, Env } from './config/env';
import { DbService } from './db/db.service';
import { HealthController } from './health/health.controller';
import {
  OnboardingController,
  OnboardingService,
  SIGNUP_LIMITER,
} from './onboarding/onboarding.controller';
import { TenancyController } from './tenancy/tenancy.controller';
import { TenancyService } from './tenancy/tenancy.service';

@Module({})
export class AppModule {
  static forRoot(config: Env): DynamicModule {
    return {
      module: AppModule,
      controllers: [HealthController, AuthController, OnboardingController, TenancyController],
      providers: [
        { provide: CONFIG, useValue: config },
        {
          provide: LOGIN_LIMITER,
          useValue: new RateLimiter(config.LOGIN_RATE_LIMIT_PER_15MIN, 15 * 60_000),
        },
        {
          provide: SIGNUP_LIMITER,
          useValue: new RateLimiter(config.SIGNUP_RATE_LIMIT_PER_HOUR, 60 * 60_000),
        },
        DbService,
        AuditService,
        AuthService,
        TenancyService,
        OnboardingService,
        { provide: APP_GUARD, useClass: SessionGuard },
        { provide: APP_FILTER, useClass: AllExceptionsFilter },
      ],
    };
  }
}
