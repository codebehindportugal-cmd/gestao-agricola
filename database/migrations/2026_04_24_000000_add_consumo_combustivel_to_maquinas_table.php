<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (! Schema::hasColumn('maquinas', 'consumo_combustivel')) {
                $table->decimal('consumo_combustivel', 10, 2)->nullable()->after('consumo_agua_ha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            if (Schema::hasColumn('maquinas', 'consumo_combustivel')) {
                $table->dropColumn('consumo_combustivel');
            }
        });
    }
};
