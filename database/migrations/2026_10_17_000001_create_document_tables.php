<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos clínicos (Fase 1): modelos por clínica e documentos emitidos (PDF preservado).
 * - Modelo editado ganha nova versão; o documento emitido guarda o TEXTO FINAL e o PDF, que não mudam depois.
 * - Documento emitido não é apagado nem reescrito: pode ser ANULADO (com motivo).
 * - FKs compostas por clínica; nomes curtos (limite de 64 caracteres do MySQL; ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->enum('category', ['prescription', 'certificate', 'guidance', 'referral', 'consent', 'other']);
            $table->string('name', 120);
            $table->string('title', 160);
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'dt_tenant_fk')->references('id')->on('tenants');
            $table->foreign('updated_by', 'dt_updated_by_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'dt_tenant_id_uq');
            $table->unique(['tenant_id', 'name'], 'dt_tenant_name_uq');
        });

        Schema::create('issued_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->char('template_id', 26)->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->enum('category', ['prescription', 'certificate', 'guidance', 'referral', 'consent', 'other']);
            $table->string('title', 160);
            $table->mediumText('body');
            $table->string('pdf_path', 255);
            $table->char('pdf_sha256', 64);
            $table->unsignedInteger('pdf_bytes');
            $table->char('verification_code', 12);
            $table->char('issued_by', 26);
            $table->char('issuer_membership_id', 26);
            $table->dateTime('issued_at');
            $table->dateTime('voided_at')->nullable();
            $table->char('voided_by', 26)->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'idoc_tenant_fk')->references('id')->on('tenants');
            $table->foreign('issued_by', 'idoc_issued_by_fk')->references('id')->on('users');
            $table->foreign('voided_by', 'idoc_voided_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'idoc_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'idoc_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'template_id'], 'idoc_template_fk')->references(['tenant_id', 'id'])->on('document_templates');
            $table->foreign(['tenant_id', 'issuer_membership_id'], 'idoc_issuer_fk')->references(['tenant_id', 'id'])->on('memberships');
            $table->unique(['tenant_id', 'id'], 'idoc_tenant_id_uq');
            $table->unique('verification_code', 'idoc_verification_uq');
            $table->index(['tenant_id', 'patient_id', 'issued_at'], 'idoc_patient_issued_idx');
            $table->index(['tenant_id', 'encounter_id'], 'idoc_encounter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('document_templates');
    }
};
