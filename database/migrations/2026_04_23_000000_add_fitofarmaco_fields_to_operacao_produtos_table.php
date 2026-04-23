<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacao_produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('operacao_produtos', 'dose')) {
                $table->decimal('dose', 10, 3)->nullable()->after('unidade_medida');
            }

            if (! Schema::hasColumn('operacao_produtos', 'dose_unidade')) {
                $table->string('dose_unidade', 50)->nullable()->after('dose');
            }

            if (! Schema::hasColumn('operacao_produtos', 'area_tratada')) {
                $table->decimal('area_tratada', 10, 2)->nullable()->after('dose_unidade');
            }

            if (! Schema::hasColumn('operacao_produtos', 'volume_calda')) {
                $table->decimal('volume_calda', 10, 2)->nullable()->after('area_tratada');
            }

            if (! Schema::hasColumn('operacao_produtos', 'finalidade')) {
                $table->string('finalidade')->nullable()->after('volume_calda');
            }

            if (! Schema::hasColumn('operacao_produtos', 'intervalo_seguranca_dias')) {
                $table->unsignedInteger('intervalo_seguranca_dias')->nullable()->after('finalidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operacao_produtos', function (Blueprint $table) {
            foreach ([
                'intervalo_seguranca_dias',
                'finalidade',
                'volume_calda',
                'area_tratada',
                'dose_unidade',
                'dose',
            ] as $column) {
                if (Schema::hasColumn('operacao_produtos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
