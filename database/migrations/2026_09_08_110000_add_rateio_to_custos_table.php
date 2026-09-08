<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custos partilhados: luz das regas, camaras frigorificas, IMI, seguros.
 *
 * Sao despesas reais da exploracao que nao pertencem a nenhuma campanha em
 * particular. Ficam sem campanha_id e marcadas como rateaveis; o
 * RateioCustosService reparte-as pelas campanhas do periodo na proporcao
 * escolhida (quilos colhidos, por omissao) para entrarem no custo/kg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custos', function (Blueprint $table) {
            if (! Schema::hasColumn('custos', 'rateavel')) {
                $table->boolean('rateavel')->default(false)->after('valor');
            }

            if (! Schema::hasColumn('custos', 'base_rateio')) {
                $table->string('base_rateio', 20)->nullable()->after('rateavel')
                    ->comment('kg ou area');
            }
        });

        Schema::table('custos', function (Blueprint $table) {
            $table->index('rateavel');
        });
    }

    public function down(): void
    {
        Schema::table('custos', function (Blueprint $table) {
            $table->dropIndex(['rateavel']);
            $table->dropColumn(['rateavel', 'base_rateio']);
        });
    }
};
