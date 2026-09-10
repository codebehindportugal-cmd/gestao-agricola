<?php

namespace App\Services;

use App\Models\Despesa;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;
use Illuminate\Support\Facades\Schema;

/**
 * Entradas de stock a partir das linhas de uma fatura (Despesa).
 *
 * Usado pelo ecra de despesas e pelo endpoint POST /api/v1/faturas, para que
 * as duas vias produzam exactamente os mesmos movimentos.
 */
class MovimentoStockService
{
    /**
     * @return array<int, array{produto: string, quantidade: float, unidade: string}>
     */
    public function processarEntradas(Despesa $despesa): array
    {
        if (! Schema::hasTable('movimento_stocks') || ! Schema::hasTable('stocks')) {
            return [];
        }

        $itemsComProduto = $despesa->items->filter(fn ($item) => $item->produto_id !== null);

        if ($itemsComProduto->isEmpty()) {
            return [];
        }

        $movimentos = [];
        $referencia = $this->referencia($despesa);

        foreach ($itemsComProduto as $item) {
            $produto = $item->produto ?? Produto::find($item->produto_id);

            if (! $produto) {
                continue;
            }

            // armazem_id = null e o stock geral
            $stock = Stock::firstOrCreate(
                ['produto_id' => $item->produto_id, 'armazem_id' => null],
                [
                    'quantidade' => 0,
                    'unidade_medida' => $produto->unidade_medida ?? 'un',
                    'data_atualizado' => now()->toDateString(),
                ]
            );

            // A fatura conta embalagens; o stock conta produto. "2 x BANJO 5 LT"
            // sao 10 litros, e o custo por litro e' um quinto do preco da
            // embalagem.
            $embalagem = $produto->conteudo_por_embalagem;
            $entrada = round((float) $item->quantidade * $embalagem, 3);
            $custoPorUnidade = round((float) $item->preco_liquido / $embalagem, 4);

            $stock->update([
                'quantidade' => max(0, (float) $stock->quantidade + $entrada),
                'data_atualizado' => now()->toDateString(),
            ]);

            MovimentoStock::create([
                'produto_id' => $item->produto_id,
                'tipo' => 'entrada',
                'quantidade' => $entrada,
                'unidade_medida' => $produto->unidade_medida ?? 'un',
                'custo_unitario' => $custoPorUnidade,
                'referencia' => $referencia,
                'despesa_id' => $despesa->id,
                'fatura_item_id' => $item->id,
                'notas' => "Entrada automática via fatura: {$referencia}",
            ]);

            $movimentos[] = [
                'produto' => $produto->nome,
                'quantidade' => $entrada,
                'unidade' => $produto->unidade_medida ?? 'un',
                'embalagens' => (float) $item->quantidade,
                'conteudo' => $embalagem,
            ];
        }

        return $movimentos;
    }

    public function reverterEntradas(Despesa $despesa): void
    {
        if (! Schema::hasTable('movimento_stocks')) {
            return;
        }

        $movimentos = MovimentoStock::query()
            ->where('despesa_id', $despesa->id)
            ->where('tipo', 'entrada')
            ->get();

        foreach ($movimentos as $movimento) {
            $stock = Stock::query()
                ->where('produto_id', $movimento->produto_id)
                ->whereNull('armazem_id')
                ->first();

            if ($stock) {
                $stock->update([
                    'quantidade' => max(0, (float) $stock->quantidade - (float) $movimento->quantidade),
                    'data_atualizado' => now()->toDateString(),
                ]);
            }

            $movimento->delete();
        }
    }

    public function referencia(Despesa $despesa): string
    {
        $partes = [];

        if ($despesa->numero_fatura) {
            $partes[] = "Fatura {$despesa->numero_fatura}";
        }

        if ($despesa->fornecedor) {
            $partes[] = "de {$despesa->fornecedor}";
        }

        return implode(' ', $partes) ?: "Fatura #{$despesa->id}";
    }
}
