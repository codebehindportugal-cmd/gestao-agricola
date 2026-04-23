<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->foreignId('funcionario_id')
                ->nullable()
                ->after('operador_id')
                ->constrained('funcionarios')
                ->setOnDelete('set null');

            $table->index('funcionario_id');
        });
    }

    public function down(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funcionario_id');
        });
    }
};
