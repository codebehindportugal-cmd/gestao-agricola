<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('culturas', function (Blueprint $table) {
            $table->string('ciclo_produtivo')->default('anual')->after('grupo_cultura');
            $table->unsignedSmallInteger('ano_inicio_producao')->nullable()->after('ciclo_produtivo');
            $table->date('data_fim_producao')->nullable()->after('ano_inicio_producao');
        });

        Schema::table('colheitas', function (Blueprint $table) {
            $table->foreignId('campanha_id')
                ->nullable()
                ->after('cultura_id')
                ->constrained('campanhas')
                ->nullOnDelete();

            $table->index('campanha_id');
        });
    }

    public function down(): void
    {
        Schema::table('colheitas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campanha_id');
        });

        Schema::table('culturas', function (Blueprint $table) {
            $table->dropColumn(['ciclo_produtivo', 'ano_inicio_producao', 'data_fim_producao']);
        });
    }
};
