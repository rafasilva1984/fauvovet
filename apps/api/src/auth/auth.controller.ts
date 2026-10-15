import { Body, Controller, Get, HttpCode, Inject, Post, Req, Res } from '@nestjs/common';
import type { Response } from 'express';
import { loginRequestSchema, selectContextRequestSchema } from '@fauvovet/contracts';
import { CONFIG, Env } from '../config/env';
import { Auth, Public, RequestId } from '../common/decorators';
import { parseBody } from '../common/errors';
import { RateLimiter } from '../common/rate-limit';
import type { AppRequest, AuthContext } from '../common/request-context';
import { AuthService } from './auth.service';
import { SESSION_COOKIE } from './tokens';

export const LOGIN_LIMITER = Symbol('LOGIN_LIMITER');

@Controller('auth')
export class AuthController {
  constructor(
    private readonly auth: AuthService,
    @Inject(CONFIG) private readonly config: Env,
    @Inject(LOGIN_LIMITER) private readonly limiter: RateLimiter,
  ) {}

  private cookieOptions() {
    return {
      httpOnly: true,
      secure: this.config.COOKIE_SECURE,
      sameSite: 'lax' as const,
      path: this.config.BASE_PATH || '/',
    };
  }

  @Public()
  @Post('login')
  @HttpCode(200)
  async login(
    @Body() body: unknown,
    @Req() req: AppRequest,
    @Res({ passthrough: true }) res: Response,
  ) {
    const input = parseBody(loginRequestSchema, body);
    this.limiter.consume(`ip:${req.ip}`);
    this.limiter.consume(`email:${input.email}`);
    const { token, expiresAt } = await this.auth.login(input.email, input.password, {
      userAgent: req.header('user-agent'),
      requestId: req.requestId,
    });
    res.cookie(SESSION_COOKIE, token, { ...this.cookieOptions(), expires: expiresAt });
    const session = await this.auth.resolveSession(token);
    return this.auth.me(session!);
  }

  @Get('me')
  me(@Auth() auth: AuthContext) {
    return this.auth.me(auth);
  }

  @Post('context')
  @HttpCode(200)
  async selectContext(
    @Auth() auth: AuthContext,
    @Body() body: unknown,
    @RequestId() requestId: string,
    @Req() req: AppRequest,
  ) {
    const input = parseBody(selectContextRequestSchema, body);
    await this.auth.selectContext(auth, input.membershipId, input.unitId, requestId);
    const token = req.cookies[SESSION_COOKIE] as string;
    const fresh = await this.auth.resolveSession(token);
    return this.auth.me(fresh!);
  }

  @Post('logout')
  @HttpCode(204)
  async logout(
    @Auth() auth: AuthContext,
    @RequestId() requestId: string,
    @Res({ passthrough: true }) res: Response,
  ) {
    await this.auth.logout(auth, requestId);
    res.clearCookie(SESSION_COOKIE, this.cookieOptions());
  }
}
