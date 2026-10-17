<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Casos científicos: séries de casos (a partir da busca avançada) e relatos de caso (a partir de um atendimento).
 * - O caso REFERENCIA o prontuário (paciente + atendimento de referência); não copia texto clínico.
 * - Cada paciente recebe um pseudônimo ("Caso 01") usado na exportação anonimizada.
 * - Consentimento do tutor para uso científico por caso; só casos com consentimento são exportados.
 * FKs compostas por clínica; nomes curtos (limite de 64 caracteres do MySQL; ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_cases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->enum('kind', ['series', 'report']);
            $table->string('title', 160);
            $table->text('question');
            $table->text('summary')->nullable();
            $table->string('specialty', 80)->nullable();
            $table->json('criteria')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedSmallInteger('next_label')->default(1);
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'cc_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'cc_created_by_fk')->references('id')->on('users');
            $table->foreign('updated_by', 'cc_updated_by_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'cc_tenant_id_uq');
            $table->index(['tenant_id', 'status', 'updated_at'], 'cc_status_updated_idx');
        });

        Schema::create('clinical_case_patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('case_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->string('label', 12);
            $table->enum('outcome', ['resolved', 'improved', 'stable', 'worsened', 'deceased', 'lost', 'unknown'])->default('unknown');
            $table->text('notes')->nullable();
            $table->enum('consent', ['pending', 'granted', 'refused'])->default('pending');
            $table->date('consent_on')->nullable();
            $table->char('consent_recorded_by', 26)->nullable();
            $table->char('added_by', 26);
            $table->timestamps();
            $table->foreign('tenant_id', 'ccp_tenant_fk')->references('id')->on('tenants');
            $table->foreign('added_by', 'ccp_added_by_fk')->references('id')->on('users');
            $table->foreign('consent_recorded_by', 'ccp_consent_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'case_id'], 'ccp_case_fk')->references(['tenant_id', 'id'])->on('clinical_cases');
            $table->foreign(['tenant_id', 'patient_id'], 'ccp_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'ccp_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->unique(['tenant_id', 'id'], 'ccp_tenant_id_uq');
            $table->unique(['tenant_id', 'case_id', 'patient_id'], 'ccp_case_patient_uq');
            $table->index(['tenant_id', 'patient_id'], 'ccp_patient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_case_patients');
        Schema::dropIfExists('clinical_cases');
    }
};
