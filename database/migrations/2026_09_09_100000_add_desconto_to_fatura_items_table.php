<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desconto por linha de fatura.
 *
 * Os fornecedores de agricultura descontam por artigo - a Casa Queridos traz
 * duas colunas, D1% e D2%, em cascata. Sem o desconto registado, o preco que
 * ia para stock era o de tabela e nao o que se pagou, e o total da despesa
 * nunca batia certo com a fatura.
 *
 * Guarda-se uma unica percentagem efectiva: dois descontos em cascata de 10%
 * e 5% valem 14,5%, que e o que interessa ao custo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fatura_items', 'desconto_percentagem')) {
                $table->decimal('desconto_percentagem', 5, 2)->default(0)->after('preco_unitario');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fatura_items', function (Blueprint $table) {
            $table->dropColumn('desconto_percentagem');
        });
    }
};
