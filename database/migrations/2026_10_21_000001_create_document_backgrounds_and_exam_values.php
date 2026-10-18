<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-020:
 *  - document_backgrounds: papel timbrado e receituário da clínica (PDF/DOCX/imagem). Original preservado com hash +
 *    fundo A4 de uma página pronto para sobrepor o texto. Trocar o fundo APOSENTA o anterior (não apaga) — os
 *    documentos já emitidos guardam o próprio PDF e o ID do fundo usado.
 *  - document_templates.background: fundo de cada modelo (automático, timbrado, receituário ou nenhum).
 *  - issued_documents.background_id: rastreabilidade do fundo usado na emissão.
 *  - exam_analytes / exam_reference_ranges / exam_result_values: exames estruturados — analitos com unidade, faixa
 *    de referência por espécie DEFINIDA PELA CLÍNICA (nada vem preenchido) e valores por pedido, com a faixa usada
 *    copiada no resultado (o laudo do laboratório manda).
 * FKs compostas por clínica; nomes curtos (limite de 64 caracteres do MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_backgrounds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->enum('kind', ['letterhead', 'prescription']);
            $table->string('source_name', 200);
            $table->string('source_mime', 100);
            $table->string('source_path', 255);
            $table->char('source_sha256', 64);
            $table->unsignedInteger('source_bytes');
            $table->string('render_path', 255);
            $table->char('render_sha256', 64);
            $table->unsignedSmallInteger('margin_top');
            $table->unsignedSmallInteger('margin_bottom');
            $table->unsignedSmallInteger('margin_side');
            $table->text('conversion_notes')->nullable();
            $table->char('uploaded_by', 26);
            $table->dateTime('uploaded_at');
            $table->dateTime('retired_at')->nullable();
            $table->char('retired_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'dbg_tenant_fk')->references('id')->on('tenants');
            $table->foreign('uploaded_by', 'dbg_uploaded_by_fk')->references('id')->on('users');
            $table->foreign('retired_by', 'dbg_retired_by_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'dbg_tenant_id_uq');
            $table->index(['tenant_id', 'kind', 'retired_at'], 'dbg_kind_idx');
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->enum('background', ['auto', 'letterhead', 'prescription', 'none'])->default('auto')->after('active');
        });

        Schema::table('issued_documents', function (Blueprint $table) {
            $table->char('background_id', 26)->nullable()->after('template_version');
            $table->foreign(['tenant_id', 'background_id'], 'idoc_background_fk')->references(['tenant_id', 'id'])->on('document_backgrounds');
        });

        Schema::create('exam_analytes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('exam_type_id', 26);
            $table->string('name', 80);
            $table->string('unit', 30)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id', 'exa_tenant_fk')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'exam_type_id'], 'exa_type_fk')->references(['tenant_id', 'id'])->on('exam_types');
            $table->unique(['tenant_id', 'id'], 'exa_tenant_id_uq');
            $table->unique(['tenant_id', 'exam_type_id', 'name'], 'exa_type_name_uq');
        });

        Schema::create('exam_reference_ranges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('analyte_id', 26);
            $table->string('species', 30); // código de App\Support\Species ou "*" (qualquer espécie)
            $table->decimal('ref_min', 14, 4)->nullable();
            $table->decimal('ref_max', 14, 4)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'exrr_tenant_fk')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'analyte_id'], 'exrr_analyte_fk')->references(['tenant_id', 'id'])->on('exam_analytes');
            $table->unique(['tenant_id', 'id'], 'exrr_tenant_id_uq');
            $table->unique(['tenant_id', 'analyte_id', 'species'], 'exrr_species_uq');
        });

        Schema::create('exam_result_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('exam_request_id', 26);
            $table->char('patient_id', 26);
            $table->char('analyte_id', 26);
            $table->decimal('value', 14, 4)->nullable();
            $table->string('value_text', 60)->nullable(); // resultado não numérico (ex.: "negativo")
            $table->string('unit', 30)->nullable();
            $table->decimal('ref_min', 14, 4)->nullable();
            $table->decimal('ref_max', 14, 4)->nullable();
            $table->enum('flag', ['low', 'normal', 'high', 'none'])->default('none');
            $table->dateTime('measured_at');
            $table->char('recorded_by', 26);
            $table->timestamps();
            $table->foreign('tenant_id', 'exv_tenant_fk')->references('id')->on('tenants');
            $table->foreign('recorded_by', 'exv_recorded_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'exam_request_id'], 'exv_request_fk')->references(['tenant_id', 'id'])->on('exam_requests');
            $table->foreign(['tenant_id', 'patient_id'], 'exv_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'analyte_id'], 'exv_analyte_fk')->references(['tenant_id', 'id'])->on('exam_analytes');
            $table->unique(['tenant_id', 'id'], 'exv_tenant_id_uq');
            $table->unique(['tenant_id', 'exam_request_id', 'analyte_id'], 'exv_request_analyte_uq');
            $table->index(['tenant_id', 'patient_id', 'analyte_id', 'measured_at'], 'exv_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_result_values');
        Schema::dropIfExists('exam_reference_ranges');
        Schema::dropIfExists('exam_analytes');
        Schema::table('issued_documents', function (Blueprint $table) {
            $table->dropForeign('idoc_background_fk');
            $table->dropColumn('background_id');
        });
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('background');
        });
        Schema::dropIfExists('document_backgrounds');
    }
};
