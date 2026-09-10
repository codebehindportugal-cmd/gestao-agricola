<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recupera os custos das faturas que entraram pela API sem campanha.
 *
 * Antes, quando o pedido nao indicava campanha, a despesa e o custo ficavam com
 * campanha_id nulo e rateavel = false: dinheiro saido que nao aparecia em
 * campanha nenhuma nem no rateio, portanto em conta nenhuma. Aqui marcam-se
 * esses custos como rateaveis, para o RateioCustosService os repartir pelos
 * quilos colhidos.
 *
 * O filtro e apertado de proposito — so custos vindos de faturas (referencia
 * externa "fatura-N"), sem campanha e sem maquina. Um custo ligado a uma
 * maquina pertence ao desgaste dela e nao se reparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('custos')
            ->whereNull('campanha_id')
            ->whereNull('maquina_id')
            ->where('rateavel', false)
            ->where('referencia_externa', 'like', 'fatura-%')
            ->update([
                'rateavel' => true,
                'base_rateio' => 'kg',
            ]);
    }

    public function down(): void
    {
        DB::table('custos')
            ->whereNull('campanha_id')
            ->whereNull('maquina_id')
            ->where('rateavel', true)
            ->where('base_rateio', 'kg')
            ->where('referencia_externa', 'like', 'fatura-%')
            ->update([
                'rateavel' => false,
                'base_rateio' => null,
            ]);
    }
};
