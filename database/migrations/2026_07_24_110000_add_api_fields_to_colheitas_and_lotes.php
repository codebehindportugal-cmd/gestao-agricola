<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colheitas', function (Blueprint $table) {
            if (! Schema::hasColumn('colheitas', 'referencia_externa')) {
                $table->string('referencia_externa')->nullable()->after('observacoes')->index();
            }
        });

        Schema::table('lotes', function (Blueprint $table) {
            if (! Schema::hasColumn('lotes', 'terreno_id')) {
                $table->foreignId('terreno_id')
                    ->nullable()
                    ->after('colheita_id')
                    ->constrained('terrenos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lotes', 'data_colheita')) {
                $table->date('data_colheita')->nullable()->after('unidade_medida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            if (Schema::hasColumn('lotes', 'data_colheita')) {
                $table->dropColumn('data_colheita');
            }

            if (Schema::hasColumn('lotes', 'terreno_id')) {
                $table->dropConstrainedForeignId('terreno_id');
            }
        });

        Schema::table('colheitas', function (Blueprint $table) {
            if (Schema::hasColumn('colheitas', 'referencia_externa')) {
                $table->dropColumn('referencia_externa');
            }
        });
    }
};
