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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colheita_id')->constrained('colheitas')->cascadeOnDelete();
            $table->foreignId('armazem_id')->nullable()->constrained('armazens')->setOnDelete('set null');
            $table->string('numero_lote')->unique();
            $table->decimal('quantidade', 10, 2);
            $table->string('unidade_medida');
            $table->date('data_entrada');
            $table->date('data_saida')->nullable();
            $table->string('localizacao_armazem')->nullable();
            $table->string('qualidade')->nullable();
            $table->enum('status', ['armazenado', 'parcialmente_vendido', 'vendido', 'devolvido', 'descartado'])->default('armazenado');
            $table->text('rastreabilidade')->nullable()->comment('JSON com histórico');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('colheita_id');
            $table->index('armazem_id');
            $table->index('data_entrada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
