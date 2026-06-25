<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimento_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->string('tipo')->default('entrada'); // entrada | saida | ajuste
            $table->decimal('quantidade', 10, 3);
            $table->string('unidade_medida', 30)->nullable();
            $table->decimal('custo_unitario', 10, 4)->nullable();
            $table->string('referencia', 500)->nullable();
            $table->foreignId('despesa_id')->nullable()->nullOnDelete()->constrained('despesas');
            $table->foreignId('fatura_item_id')->nullable()->nullOnDelete()->constrained('fatura_items');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimento_stocks');
    }
};
