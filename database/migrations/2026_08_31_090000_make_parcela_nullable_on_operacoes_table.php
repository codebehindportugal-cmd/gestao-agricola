<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ha operacoes que nao pertencem a uma parcela concreta: uma apanha de fruta
 * com 18 pessoas durante tres semanas atravessa a exploracao toda. A coluna
 * campanha_id e cultura_id ja eram nulaveis; parcela_id nao era.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->foreignId('parcela_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->foreignId('parcela_id')->nullable(false)->change();
        });
    }
};
