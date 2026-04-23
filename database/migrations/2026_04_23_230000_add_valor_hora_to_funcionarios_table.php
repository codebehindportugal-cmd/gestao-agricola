<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios', 'valor_hora')) {
                $table->decimal('valor_hora', 10, 2)->nullable()->after('tipo_contrato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('funcionarios', 'valor_hora')) {
                $table->dropColumn('valor_hora');
            }
        });
    }
};
