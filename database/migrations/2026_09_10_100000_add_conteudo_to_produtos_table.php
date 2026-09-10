<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conteudo da embalagem.
 *
 * A fatura conta embalagens ("2 x BANJO 5 LT") e o campo conta produto (10
 * litros). Sem saber quanto leva cada embalagem, o stock subia 2 em vez de 10
 * e o custo por litro ficava cinco vezes maior do que e'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'conteudo')) {
                $table->decimal('conteudo', 10, 3)->nullable()->after('unidade_medida')
                    ->comment('quanto leva cada embalagem, na unidade_medida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('conteudo');
        });
    }
};
