<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromissos', function (Blueprint $table) {
            $table->id();

            $table->string('titulo');
            $table->text('descricao')->nullable();

            // pagamento | tarefa_agricola | manutencao | prazo_legal
            $table->string('categoria')->default('pagamento');
            // etiqueta livre: IMI, IUC, Seguro, Segurança Social, Poda, IFAP, ...
            $table->string('tipo')->nullable();
            $table->string('entidade')->nullable();

            $table->date('data');
            $table->time('hora')->nullable();
            $table->decimal('valor', 12, 2)->nullable();

            // pendente | concluido | cancelado
            $table->string('estado')->default('pendente');
            $table->date('data_conclusao')->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();

            // nenhuma | mensal | trimestral | semestral | anual | personalizada
            $table->string('recorrencia')->default('nenhuma');
            $table->unsignedSmallInteger('recorrencia_intervalo')->nullable();
            $table->string('recorrencia_unidade')->nullable(); // dia | semana | mes | ano
            $table->date('recorrencia_fim')->nullable();
            $table->foreignId('compromisso_pai_id')->nullable()
                ->constrained('compromissos')->cascadeOnDelete();

            $table->unsignedSmallInteger('antecedencia_aviso_dias')->default(7);

            $table->foreignId('campanha_id')->nullable()->constrained('campanhas')->nullOnDelete();
            $table->foreignId('parcela_id')->nullable()->constrained('parcelas')->nullOnDelete();
            $table->foreignId('cultura_id')->nullable()->constrained('culturas')->nullOnDelete();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->foreignId('custo_id')->nullable()->constrained('custos')->nullOnDelete();

            $table->string('referencia_externa')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('data');
            $table->index('estado');
            $table->index('categoria');
            $table->index(['estado', 'data']);
            $table->unique('referencia_externa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromissos');
    }
};
