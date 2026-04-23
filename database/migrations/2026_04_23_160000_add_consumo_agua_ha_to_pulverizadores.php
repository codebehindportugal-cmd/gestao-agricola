<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (!Schema::hasColumn('maquinas', 'consumo_agua_ha')) {
                $table->decimal('consumo_agua_ha', 8, 2)->nullable()->after('horas_manutencao');
            }
        });

        Schema::table('alfaias', function (Blueprint $table) {
            if (!Schema::hasColumn('alfaias', 'consumo_agua_ha')) {
                $table->decimal('consumo_agua_ha', 8, 2)->nullable()->after('largura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (Schema::hasColumn('maquinas', 'consumo_agua_ha')) {
                $table->dropColumn('consumo_agua_ha');
            }
        });

        Schema::table('alfaias', function (Blueprint $table) {
            if (Schema::hasColumn('alfaias', 'consumo_agua_ha')) {
                $table->dropColumn('consumo_agua_ha');
            }
        });
    }
};
