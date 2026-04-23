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
        Schema::create('custos', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->string('tipo')->comment('material, mao_obra, maquina, energia, etc');
            $table->decimal('valor', 12, 2);
            $table->date('data_custo');
            $table->foreignId('operacao_id')->nullable()->constrained('operacoes')->setOnDelete('set null');
            $table->foreignId('cultura_id')->nullable()->constrained('culturas')->setOnDelete('set null');
            $table->foreignId('parcela_id')->nullable()->constrained('parcelas')->setOnDelete('set null');
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->setOnDelete('set null');
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->setOnDelete('set null');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('data_custo');
            $table->index('operacao_id');
            $table->index('cultura_id');
            $table->index('parcela_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custos');
    }
};
