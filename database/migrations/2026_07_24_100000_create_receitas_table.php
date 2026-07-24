<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receitas')) {
            return;
        }

        Schema::create('receitas', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->string('tipo')->comment('venda_colheita, subsidio, servico, outro');
            $table->decimal('valor', 12, 2);
            $table->date('data');
            $table->foreignId('campanha_id')->nullable()->constrained('campanhas')->nullOnDelete();
            $table->foreignId('cultura_id')->nullable()->constrained('culturas')->nullOnDelete();
            $table->foreignId('parcela_id')->nullable()->constrained('parcelas')->nullOnDelete();
            $table->foreignId('colheita_id')->nullable()->constrained('colheitas')->nullOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->string('comprador_nome')->nullable();
            $table->string('documento')->nullable();
            $table->string('referencia_externa')->nullable()->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('tipo');
            $table->index('data');
            $table->index('campanha_id');
            $table->index('cultura_id');
            $table->index('parcela_id');
            $table->index('colheita_id');
            $table->index('lote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receitas');
    }
};
