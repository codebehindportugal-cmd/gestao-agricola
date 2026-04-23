<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operacao_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->decimal('quantidade', 10, 2);
            $table->string('unidade_medida');
            $table->decimal('custo_unitario', 12, 2)->nullable();
            $table->decimal('custo_total', 12, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('operacao_id');
            $table->index('produto_id');
            $table->unique(['operacao_id', 'produto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacao_produtos');
    }
};
