<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custo de utilizacao dos recursos.
 *
 * Ate aqui uma maquina so sabia o que consumia em litros; nao havia forma de
 * dizer quanto custa uma hora de trator ou um quilometro de carrinha. Sem isso
 * a apanha aparecia com o custo da mao de obra e mais nada.
 *
 * custo_hora: combustivel + desgaste + amortizacao por hora de trabalho.
 * custo_km: alternativa para viaturas de transporte, onde o que conta e a
 * distancia e nao as horas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (! Schema::hasColumn('maquinas', 'custo_hora')) {
                $table->decimal('custo_hora', 10, 2)->nullable()->after('consumo_combustivel')
                    ->comment('EUR por hora de utilizacao (combustivel + desgaste)');
            }

            if (! Schema::hasColumn('maquinas', 'custo_km')) {
                $table->decimal('custo_km', 10, 2)->nullable()->after('custo_hora')
                    ->comment('EUR por km, para viaturas de transporte');
            }
        });

        Schema::table('alfaias', function (Blueprint $table) {
            if (! Schema::hasColumn('alfaias', 'custo_hora')) {
                $table->decimal('custo_hora', 10, 2)->nullable()->after('consumo_agua_ha')
                    ->comment('EUR por hora de utilizacao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alfaias', function (Blueprint $table) {
            if (Schema::hasColumn('alfaias', 'custo_hora')) {
                $table->dropColumn('custo_hora');
            }
        });

        Schema::table('maquinas', function (Blueprint $table) {
            foreach (['custo_km', 'custo_hora'] as $coluna) {
                if (Schema::hasColumn('maquinas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
