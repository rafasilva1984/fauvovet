<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice da busca clínica em texto livre (ADR-017). MySQL não tem tsvector/unaccent e o FULLTEXT do InnoDB ignora
 * palavras curtas, usa stopwords em inglês e não enxerga dados não confirmados — por isso uma tabela de termos
 * normalizados (sem acento, minúsculas, plural reduzido) com índice B-tree, que atende prefixo ("dermat%").
 * - clinical_search_documents: um trecho pesquisável por origem (campo do atendimento, diagnóstico, adendo, alerta,
 *   documento emitido, vacina), com o texto original (para o trecho exibido) e o normalizado (para frases exatas).
 * - clinical_search_terms: termos distintos de cada trecho.
 * - clinical_search_status: quando e com qual versão o índice da clínica foi (re)construído (rastreabilidade).
 * Tudo é derivado do prontuário e pode ser reconstruído a qualquer momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_search_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->enum('source_type', ['encounter', 'diagnosis', 'addendum', 'alert', 'document', 'vaccination']);
            $table->char('source_id', 26);
            $table->string('field', 40)->default('');
            $table->string('label', 120);
            $table->enum('dx_status', ['suspected', 'confirmed', 'ruled_out'])->nullable();
            $table->dateTime('occurred_at');
            $table->char('author_id', 26)->nullable();
            $table->mediumText('body');
            $table->mediumText('normalized');
            $table->dateTime('indexed_at');
            $table->foreign('tenant_id', 'csd_tenant_fk')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'patient_id'], 'csd_patient_fk')->references(['tenant_id', 'id'])->on('patients')->cascadeOnDelete();
            $table->unique(['tenant_id', 'id'], 'csd_tenant_id_uq');
            $table->unique(['tenant_id', 'source_type', 'source_id', 'field'], 'csd_source_uq');
            $table->index(['tenant_id', 'patient_id'], 'csd_patient_idx');
        });

        Schema::create('clinical_search_terms', function (Blueprint $table) {
            $table->char('tenant_id', 26);
            $table->string('term', 40);
            $table->char('document_id', 26);
            $table->primary(['tenant_id', 'term', 'document_id']);
            $table->foreign(['tenant_id', 'document_id'], 'cst_document_fk')->references(['tenant_id', 'id'])->on('clinical_search_documents')->cascadeOnDelete();
        });

        Schema::create('clinical_search_status', function (Blueprint $table) {
            $table->char('tenant_id', 26)->primary();
            $table->unsignedSmallInteger('version');
            $table->unsignedInteger('documents')->default(0);
            $table->dateTime('indexed_at');
            $table->foreign('tenant_id', 'css_tenant_fk')->references('id')->on('tenants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_search_status');
        Schema::dropIfExists('clinical_search_terms');
        Schema::dropIfExists('clinical_search_documents');
    }
};
