<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->string('tipo_ocupacao')->default('culturas_anuais')->after('estado');
            $table->unsignedInteger('numero_arvores')->nullable()->after('tipo_ocupacao');
            $table->decimal('compasso_linha_m', 8, 2)->nullable()->after('numero_arvores');
            $table->decimal('compasso_planta_m', 8, 2)->nullable()->after('compasso_linha_m');
        });
    }

    public function down(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_ocupacao',
                'numero_arvores',
                'compasso_linha_m',
                'compasso_planta_m',
            ]);
        });
    }
};
