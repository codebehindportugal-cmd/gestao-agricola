<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'estabelecimento_venda_nome')) {
                $table->string('estabelecimento_venda_nome')->nullable()->after('numero_autorizacao_dgav');
            }

            if (! Schema::hasColumn('produtos', 'estabelecimento_venda_autorizacao')) {
                $table->string('estabelecimento_venda_autorizacao')->nullable()->after('estabelecimento_venda_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            foreach (['estabelecimento_venda_autorizacao', 'estabelecimento_venda_nome'] as $column) {
                if (Schema::hasColumn('produtos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
