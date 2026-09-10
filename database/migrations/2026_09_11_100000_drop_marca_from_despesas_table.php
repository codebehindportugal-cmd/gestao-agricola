<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A coluna marca veio do codigo da Horta da Maria quando o modulo de faturas
 * foi copiado para ca. Nesta exploracao nao existem marcas: ha uma exploracao
 * so, e todas as despesas sao dela. A coluna ficava sempre com o valor por
 * omissao "horta_da_maria", o que nao quer dizer nada aqui e so confunde quem
 * olha para a base de dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('despesas', 'marca')) {
            return;
        }

        Schema::table('despesas', function (Blueprint $table) {
            $table->dropColumn('marca');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('despesas', 'marca')) {
            return;
        }

        Schema::table('despesas', function (Blueprint $table) {
            $table->string('marca')->nullable()->after('categoria');
        });
    }
};
