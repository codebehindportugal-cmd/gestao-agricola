<?php

namespace App\Support;

use App\Models\Operacao;
use App\Models\Produto;
use App\Models\Stock;

class StockConsumption
{
    public static function syncOperation(Operacao $operacao, array $previousProducts, array $nextProducts, ?float $previousFuel = null): void
    {
        $previous = collect($previousProducts);
        $next = collect($nextProducts);
        $productIds = $previous->keys()->merge($next->keys())->unique();

        foreach ($productIds as $productId) {
            $previousQuantity = (float) ($previous->get($productId)['quantidade'] ?? 0);
            $nextQuantity = (float) ($next->get($productId)['quantidade'] ?? 0);
            $delta = $previousQuantity - $nextQuantity;

            if (abs($delta) < 0.001) {
                continue;
            }

            $payload = $next->get($productId) ?? $previous->get($productId);
            self::adjustProductStock((int) $productId, $delta, $payload['unidade_medida'] ?? null, "Operacao {$operacao->id}.");
        }

        self::syncFuel($operacao, $previousFuel);
    }

    public static function restoreOperation(Operacao $operacao): void
    {
        foreach (self::productsFromOperation($operacao) as $productId => $payload) {
            self::adjustProductStock((int) $productId, (float) $payload['quantidade'], $payload['unidade_medida'] ?? null, "Operacao {$operacao->id} removida.");
        }

        self::adjustFuel($operacao, (float) ($operacao->combustivel_gasto_l ?? 0));
    }

    public static function reconcileOperation(Operacao $operacao): void
    {
        foreach (self::productsFromOperation($operacao) as $productId => $payload) {
            $note = "Operacao {$operacao->id}.";

            if (! self::stockHasNote((int) $productId, $note)) {
                self::adjustProductStock((int) $productId, -1 * (float) $payload['quantidade'], $payload['unidade_medida'] ?? null, $note);
            }
        }

        $fuel = (float) ($operacao->combustivel_gasto_l ?? 0);

        if ($fuel <= 0) {
            return;
        }

        $produto = self::fuelProduct();

        if (! $produto) {
            return;
        }

        $note = "Combustivel da operacao {$operacao->id}.";

        if (! self::stockHasNote($produto->id, $note)) {
            self::adjustProductStock($produto->id, -1 * $fuel, 'L', $note);
        }
    }

    public static function productsFromOperation(Operacao $operacao): array
    {
        return $operacao->produtos()
            ->get()
            ->mapWithKeys(fn (Produto $produto) => [
                $produto->id => [
                    'quantidade' => (float) $produto->pivot->quantidade,
                    'unidade_medida' => $produto->pivot->unidade_medida,
                ],
            ])
            ->all();
    }

    private static function syncFuel(Operacao $operacao, ?float $previousFuel): void
    {
        $previous = $previousFuel ?? 0;
        $current = $operacao->combustivel_gasto_l === null ? 0 : (float) $operacao->combustivel_gasto_l;
        $delta = $previous - $current;

        if (abs($delta) < 0.001) {
            return;
        }

        self::adjustFuel($operacao, $delta);
    }

    private static function adjustFuel(Operacao $operacao, float $quantityDelta): void
    {
        if (abs($quantityDelta) < 0.001) {
            return;
        }

        $produto = self::fuelProduct();

        if (! $produto) {
            return;
        }

        self::adjustProductStock($produto->id, $quantityDelta, 'L', "Combustivel da operacao {$operacao->id}.");
    }

    private static function fuelProduct(): ?Produto
    {
        return Produto::query()
            ->where('tipo', 'like', 'combust%')
            ->orWhere('nome', 'like', '%combust%')
            ->orWhere('nome', 'like', '%gasoleo%')
            ->orWhere('nome', 'like', '%diesel%')
            ->orderByRaw("CASE WHEN tipo LIKE 'combust%' THEN 0 ELSE 1 END")
            ->orderBy('nome')
            ->first();
    }

    private static function adjustProductStock(int $productId, float $delta, ?string $unit, string $notes): void
    {
        $stock = Stock::query()->firstOrNew([
            'produto_id' => $productId,
            'armazem_id' => null,
        ]);

        $stock->quantidade = (float) ($stock->quantidade ?? 0) + $delta;
        $stock->unidade_medida = $unit ?: $stock->unidade_medida ?: 'un';
        $stock->data_atualizado = now()->toDateString();
        $stock->observacoes = trim(($stock->observacoes ? "{$stock->observacoes}\n" : '').$notes);
        $stock->save();
    }

    private static function stockHasNote(int $productId, string $note): bool
    {
        return Stock::query()
            ->where('produto_id', $productId)
            ->where('armazem_id', null)
            ->where('observacoes', 'like', "%{$note}%")
            ->exists();
    }
}
