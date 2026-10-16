<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda (Fase 1). Horários gravados em UTC (DATETIME); exibição e digitação no fuso da UNIDADE.
 * Todas as referências usam FK COMPOSTA (tenant_id, x_id): unidade, paciente, tipo e profissional precisam ser
 * da mesma clínica do agendamento — o banco recusa qualquer mistura entre clínicas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 80);
            $table->unsignedSmallInteger('duration_minutes');
            $table->char('color', 7);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('unit_id', 26);
            $table->char('patient_id', 26);
            $table->char('appointment_type_id', 26);
            $table->char('professional_membership_id', 26)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['scheduled', 'confirmed', 'waiting', 'in_progress', 'completed', 'no_show', 'cancelled'])->default('scheduled');
            $table->boolean('is_walk_in')->default(false); // encaixe: pode sobrepor a agenda do profissional
            $table->string('notes', 500)->nullable(); // observação de recepção (não clínica)
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units');
            $table->foreign(['tenant_id', 'patient_id'])->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'appointment_type_id'])->references(['tenant_id', 'id'])->on('appointment_types');
            $table->foreign(['tenant_id', 'professional_membership_id'])->references(['tenant_id', 'id'])->on('memberships');
            $table->unique(['tenant_id', 'id']);
            // Nomes explícitos: o MySQL limita identificadores a 64 caracteres.
            $table->index(['tenant_id', 'unit_id', 'starts_at'], 'appointments_unit_starts_idx');
            $table->index(['tenant_id', 'professional_membership_id', 'starts_at'], 'appointments_professional_starts_idx');
            $table->index(['tenant_id', 'patient_id', 'starts_at'], 'appointments_patient_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_types');
    }
};
