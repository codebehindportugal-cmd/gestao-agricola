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
        Schema::create('culturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo')->comment('fruta, legume, etc');
            $table->string('variedade')->nullable();
            $table->date('data_plantacao');
            $table->integer('ciclo_dias')->nullable()->comment('duração esperada do ciclo');
            $table->date('previsao_colheita')->nullable();
            $table->decimal('quantidade_esperada', 10, 2)->nullable();
            $table->string('unidade_medida')->default('kg');
            $table->enum('estado', ['planejada', 'em_crescimento', 'madura', 'colhida', 'cancelada'])->default('planejada');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('parcela_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culturas');
    }
};
