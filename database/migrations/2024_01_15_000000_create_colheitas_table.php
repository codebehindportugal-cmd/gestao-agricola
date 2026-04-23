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
        Schema::create('colheitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultura_id')->constrained('culturas')->cascadeOnDelete();
            $table->foreignId('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->date('data_colheita');
            $table->decimal('quantidade_total', 10, 2);
            $table->string('unidade_medida')->default('kg');
            $table->string('qualidade')->comment('premium, superior, comercial, segunda');
            $table->decimal('quantidade_perdas', 10, 2)->nullable();
            $table->string('motivo_perdas')->nullable();
            $table->foreignId('operador_id')->nullable()->constrained('users')->setOnDelete('set null');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cultura_id');
            $table->index('parcela_id');
            $table->index('data_colheita');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colheitas');
    }
};
