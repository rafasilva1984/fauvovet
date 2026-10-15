import { Body, Controller, Get, Param, ParseUUIDPipe, Post, Put } from '@nestjs/common';
import { createUnitRequestSchema, updateThemeRequestSchema } from '@fauvovet/contracts';
import { Auth, RequestId, RequirePermission } from '../common/decorators';
import { Errors, parseBody } from '../common/errors';
import type { AuthContext } from '../common/request-context';
import { TenancyService } from './tenancy.service';

const uuidPipe = new ParseUUIDPipe({ exceptionFactory: () => Errors.notFound() });

@Controller()
export class TenancyController {
  constructor(private readonly tenancy: TenancyService) {}

  @Get('tenant')
  @RequirePermission('tenant:read')
  tenant(@Auth() auth: AuthContext) {
    return this.tenancy.getTenant(auth);
  }

  @Put('tenant/theme')
  @RequirePermission('theme:update')
  updateTheme(@Auth() auth: AuthContext, @Body() body: unknown, @RequestId() requestId: string) {
    return this.tenancy.updateTheme(auth, parseBody(updateThemeRequestSchema, body), requestId);
  }

  @Get('units')
  @RequirePermission('units:read')
  units(@Auth() auth: AuthContext) {
    return this.tenancy.listUnits(auth);
  }

  @Get('units/:id')
  @RequirePermission('units:read')
  unit(@Auth() auth: AuthContext, @Param('id', uuidPipe) id: string) {
    return this.tenancy.getUnit(auth, id);
  }

  @Post('units')
  @RequirePermission('units:create')
  createUnit(@Auth() auth: AuthContext, @Body() body: unknown, @RequestId() requestId: string) {
    return this.tenancy.createUnit(auth, parseBody(createUnitRequestSchema, body), requestId);
  }

  @Get('members')
  @RequirePermission('members:read')
  members(@Auth() auth: AuthContext) {
    return this.tenancy.listMembers(auth);
  }

  @Get('audit')
  @RequirePermission('audit:read')
  audit(@Auth() auth: AuthContext) {
    return this.tenancy.listAudit(auth);
  }
}
