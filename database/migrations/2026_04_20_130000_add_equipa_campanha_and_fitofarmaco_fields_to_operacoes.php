<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->foreignId('equipa_id')
                ->nullable()
                ->after('funcionario_id')
                ->constrained('equipas')
                ->nullOnDelete();

            $table->foreignId('campanha_id')
                ->nullable()
                ->after('cultura_id')
                ->constrained('campanhas')
                ->nullOnDelete();

            $table->index('equipa_id');
            $table->index('campanha_id');
        });

        Schema::table('operacao_produtos', function (Blueprint $table) {
            $table->decimal('dose', 10, 3)->nullable()->after('quantidade');
            $table->string('dose_unidade')->nullable()->after('dose');
            $table->decimal('area_tratada', 10, 2)->nullable()->after('dose_unidade');
            $table->decimal('volume_calda', 10, 2)->nullable()->after('area_tratada');
            $table->string('finalidade')->nullable()->after('volume_calda');
            $table->unsignedInteger('intervalo_seguranca_dias')->nullable()->after('finalidade');
        });
    }

    public function down(): void
    {
        Schema::table('operacao_produtos', function (Blueprint $table) {
            $table->dropColumn([
                'dose',
                'dose_unidade',
                'area_tratada',
                'volume_calda',
                'finalidade',
                'intervalo_seguranca_dias',
            ]);
        });

        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campanha_id');
            $table->dropConstrainedForeignId('equipa_id');
        });
    }
};
