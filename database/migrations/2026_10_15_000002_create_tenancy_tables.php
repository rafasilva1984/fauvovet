<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundação multi-tenant. Sem RLS no MySQL, o isolamento é feito em camadas (ver docs/SECURITY.md):
 *  1. toda tabela de clínica tem tenant_id + UNIQUE (tenant_id, id);
 *  2. relações entre tabelas de clínica usam FK COMPOSTA (tenant_id, x_id) → impossível ligar dados de clínicas diferentes;
 *  3. o código aplica escopo obrigatório por tenant (App\Tenancy) e um firewall de SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('trade_name', 120);
            $table->string('legal_name', 160)->nullable();
            $table->enum('person_type', ['PF', 'PJ']);
            $table->string('document_number', 14)->nullable(); // só dígitos; NÃO é único (evita enumeração entre clínicas)
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('address_line', 200)->nullable();
            $table->string('city', 80)->nullable();
            $table->char('state', 2)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']); // collation *_ci → único sem diferenciar maiúsculas
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->json('roles');
            $table->enum('status', ['active', 'invited', 'disabled'])->default('active');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'id']);
            $table->index('user_id');
        });

        // Restrição opcional de unidades por vínculo (vazio = todas as unidades da clínica).
        Schema::create('membership_units', function (Blueprint $table) {
            $table->char('tenant_id', 26);
            $table->char('membership_id', 26);
            $table->char('unit_id', 26);
            $table->primary(['membership_id', 'unit_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units');
        });

        Schema::create('tenant_themes', function (Blueprint $table) {
            $table->char('tenant_id', 26)->primary();
            $table->char('primary_color', 7);
            $table->char('secondary_color', 7);
            $table->enum('mode', ['light', 'dark', 'system'])->default('system');
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        // Auditoria somente-inclusão (o model bloqueia UPDATE/DELETE; ver docs/SECURITY.md sobre limites no MySQL).
        Schema::create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 26)->nullable();
            $table->char('actor_user_id', 26)->nullable();
            $table->string('action', 64);
            $table->string('target_type', 40)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->char('ip_hash', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('actor_user_id')->references('id')->on('users');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('tenant_themes');
        Schema::dropIfExists('membership_units');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('units');
        Schema::dropIfExists('tenants');
    }
};
