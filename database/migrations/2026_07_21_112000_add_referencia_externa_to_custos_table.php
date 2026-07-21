<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custos', function (Blueprint $table) {
            if (! Schema::hasColumn('custos', 'referencia_externa')) {
                $table->string('referencia_externa')->nullable()->after('data_custo')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('custos', function (Blueprint $table) {
            if (Schema::hasColumn('custos', 'referencia_externa')) {
                $table->dropColumn('referencia_externa');
            }
        });
    }
};
