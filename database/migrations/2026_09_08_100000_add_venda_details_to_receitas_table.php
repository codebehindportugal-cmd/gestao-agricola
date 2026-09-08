<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas de fruta com quantidade e preco.
 *
 * Ate aqui uma receita era so um valor: nao dava para saber a quantos quilos
 * correspondia, logo nao havia preco medio de venda nem margem por quilo -
 * que e o numero que interessa comparar com o custo/kg da campanha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            if (! Schema::hasColumn('receitas', 'quantidade')) {
                $table->decimal('quantidade', 12, 3)->nullable()->after('valor');
            }

            if (! Schema::hasColumn('receitas', 'unidade')) {
                $table->string('unidade', 20)->nullable()->default('kg')->after('quantidade');
            }

            if (! Schema::hasColumn('receitas', 'preco_unitario')) {
                $table->decimal('preco_unitario', 12, 4)->nullable()->after('unidade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            $table->dropColumn(['quantidade', 'unidade', 'preco_unitario']);
        });
    }
};
