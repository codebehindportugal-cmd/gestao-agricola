<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O tamanho da embalagem da linha da fatura, nao o do catalogo.
 *
 * O mesmo produto vende-se em embalagens diferentes — o BANJO vem em 5 L e em
 * 20 L — e o `conteudo` do produto e so o tamanho habitual. Sem guardar o
 * tamanho na linha, o movimento de stock ficava preso ao que estava gravado no
 * produto: foi assim que o ERUNE entrou mal, porque o produto ja existia com
 * conteudo 1 e a designacao dizia 5 L.
 *
 * Guardado na linha, o movimento e reproduzivel: apagar a despesa e voltar a
 * processa-la da os mesmos litros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fatura_items', 'conteudo_embalagem')) {
                $table->decimal('conteudo_embalagem', 12, 4)->nullable()->after('quantidade')
                    ->comment('Quanto leva cada embalagem desta linha; null usa o do produto');
            }

            if (! Schema::hasColumn('fatura_items', 'unidade_embalagem')) {
                $table->string('unidade_embalagem', 20)->nullable()->after('conteudo_embalagem');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fatura_items', function (Blueprint $table) {
            $table->dropColumn(['conteudo_embalagem', 'unidade_embalagem']);
        });
    }
};
