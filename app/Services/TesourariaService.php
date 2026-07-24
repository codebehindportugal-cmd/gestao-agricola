<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Receita;
use Illuminate\Database\Eloquent\Builder;

class TesourariaService
{
    /**
     * @return array<string, mixed>
     */
    public function resumo(?Campanha $campanha = null, ?string $de = null, ?string $ate = null): array
    {
        $receitas = Receita::query();
        $custos = Custo::query();

        if ($campanha) {
            $receitas->where('campanha_id', $campanha->id);
            $custos->where('campanha_id', $campanha->id);
        }

        $this->filtrarPeriodo($receitas, 'data', $de, $ate);
        $this->filtrarPeriodo($custos, 'data_custo', $de, $ate);

        $entradas = round((float) (clone $receitas)->sum('valor'), 2);
        $saidas = round((float) (clone $custos)->sum('valor'), 2);

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => round($entradas - $saidas, 2),
            'por_tipo_entrada' => $this->porTipo($receitas),
            'por_tipo_saida' => $this->porTipo($custos),
        ];
    }

    /**
     * @param  Builder<Receita|Custo>  $query
     */
    private function filtrarPeriodo(Builder $query, string $campoData, ?string $de, ?string $ate): void
    {
        if ($de !== null && $de !== '') {
            $query->whereDate($campoData, '>=', $de);
        }

        if ($ate !== null && $ate !== '') {
            $query->whereDate($campoData, '<=', $ate);
        }
    }

    /**
     * @param  Builder<Receita|Custo>  $query
     * @return array<string, float>
     */
    private function porTipo(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('tipo, SUM(valor) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->map(fn ($total) => round((float) $total, 2))
            ->all();
    }
}
