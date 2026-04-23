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
        Schema::create('campanhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultura_id')->constrained('culturas')->cascadeOnDelete();
            $table->integer('ano');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->enum('status', ['planejada', 'em_curso', 'concluida', 'cancelada'])->default('planejada');
            $table->decimal('producao_esperada', 10, 2)->nullable();
            $table->decimal('producao_real', 10, 2)->nullable();
            $table->decimal('custo_estimado', 12, 2)->nullable();
            $table->decimal('custo_real', 12, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cultura_id');
            $table->index('ano');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campanhas');
    }
};
