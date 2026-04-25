<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colheitas', function (Blueprint $table) {
            if (! Schema::hasColumn('colheitas', 'operacao_id')) {
                $table->foreignId('operacao_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('operacoes')
                    ->nullOnDelete();

                $table->unique('operacao_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('colheitas', function (Blueprint $table) {
            if (Schema::hasColumn('colheitas', 'operacao_id')) {
                $table->dropUnique(['operacao_id']);
                $table->dropConstrainedForeignId('operacao_id');
            }
        });
    }
};
