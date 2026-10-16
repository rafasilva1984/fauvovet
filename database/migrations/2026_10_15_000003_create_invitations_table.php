<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convites de equipe por link de uso único (Fase 1). O banco guarda só o SHA-256 do token; o link completo
 * é mostrado uma única vez a quem convidou. Envio por e-mail fica para quando houver provedor configurado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('email', 191);
            $table->json('roles');
            $table->char('token_hash', 64)->unique();
            $table->char('invited_by', 26);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->char('accepted_by', 26)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->char('revoked_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('invited_by')->references('id')->on('users');
            $table->foreign('accepted_by')->references('id')->on('users');
            $table->foreign('revoked_by')->references('id')->on('users');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
