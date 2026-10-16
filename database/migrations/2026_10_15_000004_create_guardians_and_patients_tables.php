<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsáveis (tutores) e pacientes (Fase 1). Vínculo muitos-para-muitos com FK COMPOSTA (tenant_id, x_id):
 * o banco recusa ligar um paciente a um responsável de outra clínica. Documentos e telefones guardados só com
 * dígitos (busca por dígitos); CPF NÃO é único (duplicidade é avisada na interface, sem revelar outras clínicas).
 * Dados clínicos (alergias, peso, prontuário) ficam fora destas tabelas, em módulos com permissão clínica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 160);
            $table->enum('person_type', ['PF', 'PJ'])->default('PF');
            $table->string('document_number', 14)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('phone_alt', 15)->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('address_line', 200)->nullable();
            $table->string('city', 80)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'name']); // lista padrão: ativos por nome
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'document_number']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->string('species', 24);
            $table->string('species_detail', 120)->nullable(); // ex.: "Calopsita", "Jabuti-piranga"
            $table->string('breed', 120)->nullable();
            $table->enum('sex', ['male', 'female', 'unknown'])->default('unknown');
            $table->enum('neutered', ['yes', 'no', 'unknown'])->default('unknown');
            $table->date('birth_date')->nullable();
            $table->boolean('birth_date_estimated')->default(false);
            $table->string('coat', 80)->nullable();
            $table->string('microchip', 30)->nullable();
            $table->string('marks', 500)->nullable();
            $table->enum('status', ['active', 'deceased', 'archived'])->default('active');
            $table->date('deceased_on')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'name']); // lista padrão e contagem do painel
            $table->index(['tenant_id', 'species']);
            $table->index(['tenant_id', 'microchip']);
        });

        Schema::create('guardian_patient', function (Blueprint $table) {
            $table->char('tenant_id', 26);
            $table->char('guardian_id', 26);
            $table->char('patient_id', 26);
            $table->enum('relationship', ['primary', 'co_guardian', 'authorized'])->default('primary');
            $table->timestamps();
            $table->primary(['patient_id', 'guardian_id']);
            $table->index(['tenant_id', 'guardian_id']);
            $table->foreign(['tenant_id', 'guardian_id'])->references(['tenant_id', 'id'])->on('guardians');
            $table->foreign(['tenant_id', 'patient_id'])->references(['tenant_id', 'id'])->on('patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_patient');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('guardians');
    }
};
