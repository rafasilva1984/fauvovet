<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prontuário (Fase 1): atendimentos, diagnósticos, adendos, peso e alertas clínicos.
 * - Toda referência entre tabelas de clínica usa FK COMPOSTA (tenant_id, x_id).
 * - Atendimento FINALIZADO não é reescrito: correções entram como adendos (somente inclusão).
 * - Sinais vitais em colunas (não JSON) para permitir busca e gráficos na Fase 2.
 * Nomes de índice/FK explícitos e curtos (limite de 64 caracteres do MySQL; ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('unit_id', 26);
            $table->char('patient_id', 26);
            $table->char('appointment_id', 26)->nullable();
            $table->char('appointment_type_id', 26)->nullable();
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->text('chief_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('physical_exam')->nullable();
            $table->text('plan')->nullable();
            $table->text('guidance')->nullable();
            $table->decimal('weight_kg', 7, 3)->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('capillary_refill_s', 3, 1)->nullable();
            $table->string('mucous_membranes', 40)->nullable();
            $table->string('hydration', 40)->nullable();
            $table->unsignedTinyInteger('body_condition_score')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finalized_at')->nullable();
            $table->char('finalized_by', 26)->nullable();
            $table->char('signer_membership_id', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'enc_tenant_fk')->references('id')->on('tenants');
            $table->foreign('finalized_by', 'enc_finalized_by_fk')->references('id')->on('users');
            $table->foreign('created_by', 'enc_created_by_fk')->references('id')->on('users');
            $table->foreign('updated_by', 'enc_updated_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'unit_id'], 'enc_unit_fk')->references(['tenant_id', 'id'])->on('units');
            $table->foreign(['tenant_id', 'patient_id'], 'enc_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'appointment_id'], 'enc_appointment_fk')->references(['tenant_id', 'id'])->on('appointments');
            $table->foreign(['tenant_id', 'appointment_type_id'], 'enc_type_fk')->references(['tenant_id', 'id'])->on('appointment_types');
            $table->foreign(['tenant_id', 'signer_membership_id'], 'enc_signer_fk')->references(['tenant_id', 'id'])->on('memberships');
            $table->unique(['tenant_id', 'id'], 'enc_tenant_id_uq');
            $table->unique(['tenant_id', 'appointment_id'], 'enc_appointment_uq'); // 1 atendimento por agendamento
            $table->index(['tenant_id', 'patient_id', 'started_at'], 'enc_patient_started_idx');
            $table->index(['tenant_id', 'status', 'created_by'], 'enc_status_author_idx');
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('encounter_id', 26);
            $table->char('patient_id', 26);
            $table->string('description', 200);
            $table->string('code', 30)->nullable();
            $table->enum('status', ['suspected', 'confirmed', 'ruled_out']);
            $table->unsignedTinyInteger('position')->default(0);
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'dx_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'dx_created_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'encounter_id'], 'dx_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'patient_id'], 'dx_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->unique(['tenant_id', 'id'], 'dx_tenant_id_uq');
            $table->index(['tenant_id', 'patient_id', 'status'], 'dx_patient_status_idx');
        });

        Schema::create('encounter_addenda', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('encounter_id', 26);
            $table->text('body');
            $table->char('author_id', 26);
            $table->char('author_membership_id', 26);
            $table->dateTime('created_at');
            $table->foreign('tenant_id', 'add_tenant_fk')->references('id')->on('tenants');
            $table->foreign('author_id', 'add_author_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'encounter_id'], 'add_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'author_membership_id'], 'add_membership_fk')->references(['tenant_id', 'id'])->on('memberships');
            $table->unique(['tenant_id', 'id'], 'add_tenant_id_uq');
            $table->index(['tenant_id', 'encounter_id', 'created_at'], 'add_encounter_created_idx');
        });

        Schema::create('patient_weights', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->decimal('weight_kg', 7, 3);
            $table->dateTime('measured_at');
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'pw_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'pw_created_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'pw_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'pw_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->unique(['tenant_id', 'id'], 'pw_tenant_id_uq');
            $table->unique(['tenant_id', 'encounter_id'], 'pw_encounter_uq');
            $table->index(['tenant_id', 'patient_id', 'measured_at'], 'pw_patient_measured_idx');
        });

        Schema::create('patient_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->enum('kind', ['allergy', 'chronic', 'medication', 'behavior', 'other']);
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->string('description', 200);
            $table->dateTime('resolved_at')->nullable();
            $table->char('resolved_by', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'pa_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'pa_created_by_fk')->references('id')->on('users');
            $table->foreign('resolved_by', 'pa_resolved_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'pa_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->unique(['tenant_id', 'id'], 'pa_tenant_id_uq');
            $table->index(['tenant_id', 'patient_id', 'resolved_at'], 'pa_patient_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_alerts');
        Schema::dropIfExists('patient_weights');
        Schema::dropIfExists('encounter_addenda');
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('encounters');
    }
};
