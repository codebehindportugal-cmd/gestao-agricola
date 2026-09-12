<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma apanha, varias colheitas.
 *
 * colheitas.operacao_id era unico: uma operacao de apanha so podia dar uma
 * colheita. Mas uma apanha real dura dias e passa por varios pomares, e as
 * colheitas registam-se por pomar. Com a restricao unica, o custo da apanha
 * caia todo na primeira colheita - 7 680 EUR em 1 200 kg dava 6,40 EUR/kg -
 * e os restantes pomares apareciam sem custo nenhum.
 *
 * Sem o unico, a operacao pode ter N colheitas e o custo reparte-se pelos
 * quilos de cada uma (ver Colheita::getCustoApanhaAttribute).
 *
 * O indice simples entra ANTES de o unico sair: no MySQL a chave estrangeira
 * de operacao_id usa esse indice e a base de dados recusa deixar a coluna sem
 * nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('colheitas', 'colheitas_operacao_id_index')) {
            Schema::table('colheitas', function (Blueprint $table) {
                $table->index('operacao_id', 'colheitas_operacao_id_index');
            });
        }

        if (Schema::hasIndex('colheitas', 'colheitas_operacao_id_unique')) {
            Schema::table('colheitas', function (Blueprint $table) {
                $table->dropUnique('colheitas_operacao_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Voltar atras so e possivel se nenhuma operacao tiver ficado com mais
        // de uma colheita; caso contrario o unico nao pode ser recriado e e
        // preferivel falhar aqui do que apagar colheitas em silencio.
        if (! Schema::hasIndex('colheitas', 'colheitas_operacao_id_unique')) {
            Schema::table('colheitas', function (Blueprint $table) {
                $table->unique('operacao_id', 'colheitas_operacao_id_unique');
            });
        }

        if (Schema::hasIndex('colheitas', 'colheitas_operacao_id_index')) {
            Schema::table('colheitas', function (Blueprint $table) {
                $table->dropIndex('colheitas_operacao_id_index');
            });
        }
    }
};
