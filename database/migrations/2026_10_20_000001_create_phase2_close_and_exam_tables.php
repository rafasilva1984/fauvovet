<?php

use App\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a Fase 2 e abre a Fase 3 (ADR-019):
 *  - clinical_search_synonyms: sinônimos DEFINIDOS PELA CLÍNICA (nada vem pronto — §3.3 ex. 6: nunca inventar
 *    correspondência clínica sem fonte);
 *  - saved_searches: pesquisas salvas (privadas do usuário ou compartilhadas com a clínica);
 *  - document_templates.source_*: original do modelo importado (DOCX/PDF) preservado em disco privado com hash;
 *  - exam_types / exam_requests: catálogo e pedidos de exame com situação (solicitado → coletado → em análise →
 *    recebido → revisado; ou cancelado), resultado e interpretação, laudo anexado opcional.
 * FKs compostas por clínica; nomes curtos (limite de 64 caracteres do MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_search_synonyms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->text('terms'); // uma expressão por linha, como a clínica escreveu
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'syn_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'syn_created_by_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'syn_tenant_id_uq');
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->string('name', 80);
            $table->json('params');
            $table->boolean('shared')->default(false);
            $table->timestamps();
            $table->foreign('tenant_id', 'ss_tenant_fk')->references('id')->on('tenants');
            $table->foreign('user_id', 'ss_user_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'ss_tenant_id_uq');
            $table->index(['tenant_id', 'user_id'], 'ss_user_idx');
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('source_name', 200)->nullable()->after('active');
            $table->string('source_mime', 100)->nullable()->after('source_name');
            $table->string('source_path', 255)->nullable()->after('source_mime');
            $table->char('source_sha256', 64)->nullable()->after('source_path');
            $table->unsignedInteger('source_bytes')->nullable()->after('source_sha256');
            $table->dateTime('imported_at')->nullable()->after('source_bytes');
            $table->char('imported_by', 26)->nullable()->after('imported_at');
        });

        Schema::create('exam_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->enum('category', ['lab', 'imaging', 'other'])->default('lab');
            $table->string('sample', 80)->nullable(); // material: sangue, urina, fezes...
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id', 'ext_tenant_fk')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'id'], 'ext_tenant_id_uq');
            $table->unique(['tenant_id', 'name'], 'ext_tenant_name_uq');
        });

        Schema::create('exam_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->char('exam_type_id', 26);
            $table->enum('status', ['requested', 'collected', 'in_analysis', 'received', 'reviewed', 'cancelled'])->default('requested');
            $table->string('lab_name', 120)->nullable();
            $table->string('request_notes', 500)->nullable();
            $table->char('requested_by', 26);
            $table->dateTime('requested_at');
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->mediumText('result_text')->nullable();
            $table->char('result_attachment_id', 26)->nullable();
            $table->char('result_by', 26)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->text('interpretation')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'exr_tenant_fk')->references('id')->on('tenants');
            $table->foreign('requested_by', 'exr_requested_by_fk')->references('id')->on('users');
            $table->foreign('reviewed_by', 'exr_reviewed_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'exr_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'exr_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'exam_type_id'], 'exr_type_fk')->references(['tenant_id', 'id'])->on('exam_types');
            $table->foreign(['tenant_id', 'result_attachment_id'], 'exr_attachment_fk')->references(['tenant_id', 'id'])->on('attachments');
            $table->unique(['tenant_id', 'id'], 'exr_tenant_id_uq');
            $table->index(['tenant_id', 'status', 'requested_at'], 'exr_status_idx');
            $table->index(['tenant_id', 'patient_id', 'requested_at'], 'exr_patient_idx');
        });

        Schema::table('clinical_search_documents', function (Blueprint $table) {
            $table->enum('source_type', ['encounter', 'diagnosis', 'addendum', 'alert', 'document', 'vaccination', 'attachment', 'exam'])->change();
        });
    }

    public function down(): void
    {
        app(TenantContext::class)->runAsSystem(fn () => DB::table('clinical_search_documents')->where('source_type', 'exam')->delete());
        Schema::table('clinical_search_documents', function (Blueprint $table) {
            $table->enum('source_type', ['encounter', 'diagnosis', 'addendum', 'alert', 'document', 'vaccination', 'attachment'])->change();
        });
        Schema::dropIfExists('exam_requests');
        Schema::dropIfExists('exam_types');
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn(['source_name', 'source_mime', 'source_path', 'source_sha256', 'source_bytes', 'imported_at', 'imported_by']);
        });
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('clinical_search_synonyms');
    }
};
