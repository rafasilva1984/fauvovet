<?php

use App\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Texto extraído de anexos PDF/DOCX para a busca clínica (CLAUDE.md §3.1/§3.3 ex. 4 e 10; ADR-018).
 * O arquivo original continua intocado; o texto extraído fica separado, com situação explícita:
 * indexed (texto pesquisável), no_text (imagem ou PDF digitalizado: "sem texto pesquisável"), failed, too_large.
 * NULL = ainda não lido (anexos anteriores a esta versão; lidos pelo botão "Ler anexos pendentes").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('text_status', 20)->nullable()->after('storage_path');
            $table->mediumText('extracted_text')->nullable()->after('text_status');
            $table->unsignedInteger('text_chars')->nullable()->after('extracted_text');
            $table->dateTime('text_extracted_at')->nullable()->after('text_chars');
            $table->index(['tenant_id', 'text_status'], 'att_text_status_idx');
        });
        Schema::table('clinical_search_documents', function (Blueprint $table) {
            $table->enum('source_type', ['encounter', 'diagnosis', 'addendum', 'alert', 'document', 'vaccination', 'attachment'])->change();
        });
    }

    public function down(): void
    {
        // Trechos de anexo precisam sair antes de encolher o enum (o índice é derivado: reconstruível).
        app(TenantContext::class)->runAsSystem(fn () => DB::table('clinical_search_documents')->where('source_type', 'attachment')->delete());
        Schema::table('clinical_search_documents', function (Blueprint $table) {
            $table->enum('source_type', ['encounter', 'diagnosis', 'addendum', 'alert', 'document', 'vaccination'])->change();
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('att_text_status_idx');
            $table->dropColumn(['text_status', 'extracted_text', 'text_chars', 'text_extracted_at']);
        });
    }
};
