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
        Schema::create('operacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->foreignId('cultura_id')->nullable()->constrained('culturas')->setOnDelete('set null');
            $table->string('tipo')->comment('mobilização do solo, sementeira, rega, fertilização, tratamento fitossanitário, poda, limpeza, colheita, etc');
            $table->dateTime('data_hora_inicio');
            $table->dateTime('data_hora_fim')->nullable();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->setOnDelete('set null');
            $table->foreignId('alfaia_id')->nullable()->constrained('alfaias')->setOnDelete('set null');
            $table->foreignId('operador_id')->nullable()->constrained('users')->setOnDelete('set null');
            $table->decimal('duracao_horas', 10, 2)->nullable();
            $table->decimal('custo_estimado', 12, 2)->nullable();
            $table->decimal('custo_real', 12, 2)->nullable();
            $table->enum('estado', ['planejada', 'em_curso', 'concluida', 'cancelada'])->default('planejada');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('parcela_id');
            $table->index('cultura_id');
            $table->index('maquina_id');
            $table->index('operador_id');
            $table->index('data_hora_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operacoes');
    }
};
