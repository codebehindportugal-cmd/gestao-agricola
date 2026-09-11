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
     * @return array<int, array{produto: string, quantidade: float, quantidade_base: float,
     *               unidade: string, embalagens: float, conteudo_embalagem: float,
     *               preco_embalagem: float, custo_por_unidade_base: float}>
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
                    'unidade_medida' => $produto->unidade_medida ?: 'un',
                    'data_atualizado' => now()->toDateString(),
                ]
            );

            // A fatura conta embalagens; o stock conta produto. "2 x BANJO 5 LT"
            // sao 10 litros, e o custo por litro e' um quinto do preco da
            // embalagem.
            //
            // O tamanho vem da linha quando ela o traz, e so depois do produto:
            // o mesmo artigo vende-se em 5 L e em 20 L, e foi por confiar
            // sempre no catalogo que o ERUNE entrou a 1 L.
            $embalagem = $item->conteudo_efetivo;
            $entrada = $item->quantidade_base;
            $custoPorUnidade = $item->custo_por_unidade_base;
            $unidade = $produto->unidade_medida ?: ($item->unidade_embalagem ?: 'un');

            $stock->update([
                'quantidade' => max(0, (float) $stock->quantidade + $entrada),
                'data_atualizado' => now()->toDateString(),
            ]);

            MovimentoStock::create([
                'produto_id' => $item->produto_id,
                'tipo' => 'entrada',
                'quantidade' => $entrada,
                'unidade_medida' => $unidade,
                'custo_unitario' => $custoPorUnidade,
                'referencia' => $referencia,
                'despesa_id' => $despesa->id,
                'fatura_item_id' => $item->id,
                'notas' => "Entrada automática via fatura: {$referencia}",
            ]);

            // Quem envia a fatura precisa de confirmar os 10 L a 20,70 EUR/L
            // sem ir a listagem do stock, por isso vai tudo no movimento.
            $movimentos[] = [
                'produto' => $produto->nome,
                'quantidade' => $entrada,
                'quantidade_base' => $entrada,
                'unidade' => $unidade,
                'embalagens' => (float) $item->quantidade,
                'conteudo' => $embalagem,
                'conteudo_embalagem' => $embalagem,
                'preco_embalagem' => (float) $item->preco_liquido,
                'custo_por_unidade_base' => $custoPorUnidade,
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
