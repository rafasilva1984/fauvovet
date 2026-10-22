<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescrição estruturada (Fase 3, ADR-022): catálogo de medicamentos da clínica (nada pré-carregado: doses e faixas
 * são definidas pela própria clínica) e itens da receita, gravados junto com o documento emitido.
 * Item de receita é registro clínico: não é editado nem apagado — anula-se a receita (documento) e emite-se outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->string('active_ingredient', 120)->nullable();
            $table->string('drug_class', 120)->nullable(); // usada para cruzar com alergias registradas
            $table->string('form', 20); // App\Models\Medication::FORMS
            $table->decimal('concentration_mg', 12, 4)->nullable(); // mg por comprimido/cápsula ou por mL
            $table->string('presentation', 120)->nullable(); // texto livre: "Comprimido 50 mg", "Frasco 60 mL"
            $table->decimal('dose_mg_kg', 10, 4)->nullable(); // dose usual da clínica (sugestão ao prescrever)
            $table->decimal('dose_min_mg_kg', 10, 4)->nullable();
            $table->decimal('dose_max_mg_kg', 10, 4)->nullable();
            $table->string('route', 30)->nullable();
            $table->string('frequency', 40)->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->json('species'); // [] = todas
            $table->boolean('controlled')->default(false);
            $table->string('notes', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'med_tenant_fk')->references('id')->on('tenants');
            $table->foreign('updated_by', 'med_updated_by_fk')->references('id')->on('users');
            $table->unique(['tenant_id', 'id'], 'med_tenant_id_uq');
            $table->unique(['tenant_id', 'name'], 'med_tenant_name_uq');
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('document_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->char('medication_id', 26)->nullable(); // null = digitado na hora (fora do catálogo)
            $table->unsignedTinyInteger('position');
            $table->string('name', 160);
            $table->string('form', 20)->nullable();
            $table->string('presentation', 160)->nullable();
            $table->decimal('concentration_mg', 12, 4)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable(); // peso usado no cálculo (fica gravado)
            $table->decimal('dose_mg_kg', 10, 4)->nullable();
            $table->decimal('dose_mg', 12, 4)->nullable(); // dose efetiva, depois do arredondamento da quantidade
            $table->decimal('quantity', 10, 4)->nullable(); // comprimidos/cápsulas/mL por administração
            $table->string('dose_text', 160);
            $table->string('route', 30);
            $table->string('frequency', 40);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->boolean('continuous')->default(false);
            $table->string('instructions', 500)->nullable();
            $table->boolean('controlled')->default(false);
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = uso contínuo ou sem duração definida
            $table->char('created_by', 26);
            $table->dateTime('created_at');
            $table->foreign('tenant_id', 'rxi_tenant_fk')->references('id')->on('tenants');
            $table->foreign('created_by', 'rxi_created_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'document_id'], 'rxi_document_fk')->references(['tenant_id', 'id'])->on('issued_documents');
            $table->foreign(['tenant_id', 'patient_id'], 'rxi_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'rxi_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->foreign(['tenant_id', 'medication_id'], 'rxi_medication_fk')->references(['tenant_id', 'id'])->on('medications');
            $table->unique(['tenant_id', 'id'], 'rxi_tenant_id_uq');
            $table->index(['tenant_id', 'patient_id', 'starts_on'], 'rxi_patient_idx');
            $table->index(['tenant_id', 'document_id', 'position'], 'rxi_document_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('medications');
    }
};
