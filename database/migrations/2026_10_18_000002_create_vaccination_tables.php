<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vacinação (Fase 3 antecipada): catálogo de vacinas da clínica e aplicações (carteira).
 * Aplicação é registro clínico: não é editada nem apagada — correção = anular com motivo e registrar de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->json('species'); // códigos de App\Support\Species; [] = todas
            $table->string('manufacturer', 120)->nullable();
            $table->unsignedTinyInteger('series_doses')->default(1);
            $table->unsignedSmallInteger('series_interval_days')->default(21);
            $table->unsignedSmallInteger('booster_interval_days')->nullable(); // null = sem reforço
            $table->string('notes', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id', 'vac_tenant_fk')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'id'], 'vac_tenant_id_uq');
            $table->unique(['tenant_id', 'name'], 'vac_tenant_name_uq');
        });

        Schema::create('vaccinations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('vaccine_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->unsignedTinyInteger('dose_number');
            $table->date('applied_on');
            $table->string('lot', 60);
            $table->string('manufacturer', 120)->nullable();
            $table->date('lot_expires_on')->nullable();
            $table->string('site', 60)->nullable(); // local de aplicação
            $table->date('next_due_on')->nullable();
            $table->string('notes', 300)->nullable();
            $table->char('applied_by_membership_id', 26);
            $table->char('created_by', 26);
            $table->dateTime('voided_at')->nullable();
            $table->char('voided_by', 26)->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'vx_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'vx_created_by_fk')->references('id')->on('users');
            $table->foreign('voided_by', 'vx_voided_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'vx_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'vaccine_id'], 'vx_vaccine_fk')->references(['tenant_id', 'id'])->on('vaccines');
            $table->foreign(['tenant_id', 'encounter_id'], 'vx_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'applied_by_membership_id'], 'vx_applied_by_fk')->references(['tenant_id', 'id'])->on('memberships');
            $table->unique(['tenant_id', 'id'], 'vx_tenant_id_uq');
            $table->index(['tenant_id', 'patient_id', 'vaccine_id', 'applied_on'], 'vx_patient_vaccine_idx');
            $table->index(['tenant_id', 'next_due_on'], 'vx_next_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
        Schema::dropIfExists('vaccines');
    }
};
