<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A especie da venda (Pereira, Macieira) quando a venda nao esta ligada a
 * nenhuma cultura nem lote.
 *
 * As vendas importadas do Moloni trazem o nome do artigo ("Pera Rocha cal.
 * 65"), nao o pomar de onde a fruta saiu. Ligá-las a uma cultura qualquer
 * dava a margem toda a um pomar so; com a especie, a venda entra na linha
 * certa do resumo por especie sem inventar a parcela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            if (! Schema::hasColumn('receitas', 'especie')) {
                $table->string('especie', 50)->nullable()->after('lote_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            if (Schema::hasColumn('receitas', 'especie')) {
                $table->dropColumn('especie');
            }
        });
    }
};
