<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Endereço do responsável em partes (rua vem do CEP; número, complemento e bairro separados). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('address_number', 20)->nullable()->after('address_line');
            $table->string('address_complement', 80)->nullable()->after('address_number');
            $table->string('district', 80)->nullable()->after('address_complement');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['address_number', 'address_complement', 'district']);
        });
    }
};
