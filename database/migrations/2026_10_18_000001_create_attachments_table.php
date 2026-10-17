<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos do prontuário (exames, imagens, documentos externos). Arquivo em disco PRIVADO com nome aleatório;
 * o banco guarda tipo detectado pelo conteúdo, tamanho e SHA-256. "Remover" só oculta (o arquivo é mantido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenant_id', 26);
            $table->char('patient_id', 26);
            $table->char('encounter_id', 26)->nullable();
            $table->enum('category', ['exam', 'image', 'document', 'other']);
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->string('original_name', 200);
            $table->string('mime', 100);
            $table->string('extension', 10);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('storage_path', 255);
            $table->char('uploaded_by', 26);
            $table->dateTime('removed_at')->nullable();
            $table->char('removed_by', 26)->nullable();
            $table->string('removed_reason', 200)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'att_tenant_fk')->references('id')->on('tenants');
            $table->foreign('uploaded_by', 'att_uploaded_by_fk')->references('id')->on('users');
            $table->foreign('removed_by', 'att_removed_by_fk')->references('id')->on('users');
            $table->foreign(['tenant_id', 'patient_id'], 'att_patient_fk')->references(['tenant_id', 'id'])->on('patients');
            $table->foreign(['tenant_id', 'encounter_id'], 'att_encounter_fk')->references(['tenant_id', 'id'])->on('encounters');
            $table->unique(['tenant_id', 'id'], 'att_tenant_id_uq');
            $table->index(['tenant_id', 'patient_id', 'removed_at', 'created_at'], 'att_patient_idx');
            $table->index(['tenant_id', 'encounter_id'], 'att_encounter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
