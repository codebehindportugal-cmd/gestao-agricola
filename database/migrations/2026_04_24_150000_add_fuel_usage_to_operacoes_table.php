<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('operacoes', 'distancia_km')) {
                $table->decimal('distancia_km', 10, 2)->nullable()->after('duracao_horas');
            }

            if (! Schema::hasColumn('operacoes', 'combustivel_gasto_l')) {
                $table->decimal('combustivel_gasto_l', 10, 2)->nullable()->after('distancia_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            if (Schema::hasColumn('operacoes', 'combustivel_gasto_l')) {
                $table->dropColumn('combustivel_gasto_l');
            }

            if (Schema::hasColumn('operacoes', 'distancia_km')) {
                $table->dropColumn('distancia_km');
            }
        });
    }
};
