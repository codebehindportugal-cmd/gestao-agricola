<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campanhas gerais: uma campanha por especie e ano, cobrindo varias parcelas.
 *
 * Ate aqui a cadeia era rigida - campanha -> uma cultura -> uma parcela - o que
 * obrigava a uma campanha por parcela. Uma apanha de pereiras atravessa dez
 * parcelas de uma vez e nao cabia no modelo.
 *
 * Esta migracao so alarga o esquema; nao mexe em dados. A passagem das
 * campanhas existentes para as gerais e feita pelo comando
 * agri:migrar-campanhas, que se corre deliberadamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanhas', function (Blueprint $table) {
            // Uma campanha sem cultura precisa de nome proprio: ate agora o
            // nome era derivado da cultura.
            $table->string('nome')->nullable()->after('id');
            $table->foreignId('cultura_id')->nullable()->change();
        });

        Schema::create('campanha_parcela', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            $table->foreignId('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campanha_id', 'parcela_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_parcela');

        Schema::table('campanhas', function (Blueprint $table) {
            $table->dropColumn('nome');
        });
    }
};
