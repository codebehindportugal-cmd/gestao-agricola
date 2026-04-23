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
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo')->comment('fertilizante, fitofarmaco, semente, combustivel, etc');
            $table->string('codigo_interno')->nullable()->unique();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->setOnDelete('set null');
            $table->decimal('custo_unitario', 12, 2)->nullable();
            $table->string('unidade_medida')->default('kg');
            $table->integer('stock_minimo')->default(0);
            $table->text('descricao')->nullable();
            $table->date('data_validade')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('fornecedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
