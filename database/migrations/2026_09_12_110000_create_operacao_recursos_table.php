<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recursos usados numa operacao: N maquinas e N alfaias, nao uma de cada.
 *
 * As colunas maquina_id e alfaia_id da tabela operacoes so aguentam um recurso.
 * Uma apanha com dois tratores, dois empilhadores de campo e um carro de
 * transporte nao cabia la, e o custo desses recursos ficava de fora do custo
 * da campanha.
 *
 * Esta tabela passa a ser a lista completa dos recursos de cada operacao. As
 * colunas antigas ficam como recurso principal (o caderno de campo DGAV e os
 * ecras existentes continuam a le-las) e sao alimentadas pela primeira linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacao_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes')->cascadeOnDelete();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
            $table->foreignId('alfaia_id')->nullable()->constrained('alfaias')->nullOnDelete();

            // Recurso que nao esta no cadastro (um carro alugado, um trator
            // emprestado): fica so com nome e custo, sem obrigar a criar ficha.
            $table->string('nome')->nullable();
            $table->string('papel')->nullable()->comment('apanha, transporte, carga, etc');
            $table->unsignedInteger('unidades')->default(1)->comment('quantos iguais, se nao estiverem no cadastro');
            $table->decimal('horas', 10, 2)->nullable()->comment('horas totais do recurso na operacao');
            $table->decimal('km', 10, 2)->nullable();

            // Valores gravados no momento (snapshot): mudar o custo/hora da
            // maquina amanha nao pode reescrever o custo de uma apanha de 2025.
            $table->decimal('custo_hora', 10, 2)->nullable();
            $table->decimal('custo_km', 10, 2)->nullable();
            $table->decimal('custo_total', 12, 2)->default(0);

            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['operacao_id', 'maquina_id']);
            $table->index('alfaia_id');
        });

        // As operacoes que ja existem passam a ter a sua maquina/alfaia tambem
        // na lista, para os ecras novos nao as perderem. Sem custo: nunca foi
        // calculado nenhum, e inventar um agora falseava campanhas fechadas.
        DB::table('operacoes')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNotNull('maquina_id')->orWhereNotNull('alfaia_id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($operacoes) {
                $linhas = [];

                foreach ($operacoes as $operacao) {
                    $linhas[] = [
                        'operacao_id' => $operacao->id,
                        'maquina_id' => $operacao->maquina_id,
                        'alfaia_id' => $operacao->alfaia_id,
                        'nome' => null,
                        'papel' => null,
                        'unidades' => 1,
                        'horas' => $operacao->duracao_horas,
                        'km' => $operacao->distancia_km,
                        'custo_hora' => null,
                        'custo_km' => null,
                        'custo_total' => 0,
                        'observacoes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($linhas !== []) {
                    DB::table('operacao_recursos')->insert($linhas);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacao_recursos');
    }
};
