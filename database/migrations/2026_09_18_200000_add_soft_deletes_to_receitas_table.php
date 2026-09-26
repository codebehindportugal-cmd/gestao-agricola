<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas apagadas pela API tem de dar para repor.
 *
 * As despesas, custos e colheitas ja tinham soft delete; as receitas nao, e um
 * DELETE numa venda seria definitivo. Numa venda de fruta isso e uma linha de
 * receita perdida, que ninguem sabe reconstituir sem ir a guia de remessa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            if (! Schema::hasColumn('receitas', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('receitas', function (Blueprint $table) {
            if (Schema::hasColumn('receitas', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
