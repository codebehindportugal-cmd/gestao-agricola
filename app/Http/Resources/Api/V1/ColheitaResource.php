<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ColheitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data_colheita?->toDateString(),
            'quantidade_total' => $this->quantidade_total,
            'unidade_medida' => $this->unidade_medida,
            'qualidade' => $this->qualidade,
            'referencia_externa' => $this->referencia_externa,
            'observacoes' => $this->observacoes,
            'campanha' => $this->whenLoaded('campanha', fn () => $this->campanha ? [
                'id' => $this->campanha->id,
                'ano' => $this->campanha->ano,
            ] : null),
            'cultura' => $this->whenLoaded('cultura', fn () => $this->cultura ? [
                'id' => $this->cultura->id,
                'nome' => $this->cultura->nome,
            ] : null),
            'parcela' => $this->whenLoaded('parcela', fn () => $this->parcela ? [
                'id' => $this->parcela->id,
                'nome' => $this->parcela->nome,
            ] : null),
            'lotes' => $this->whenLoaded('lotes', fn () => $this->lotes->map(fn ($lote) => [
                'id' => $lote->id,
                'codigo' => $lote->numero_lote,
                'terreno' => $lote->relationLoaded('terreno') && $lote->terreno ? [
                    'id' => $lote->terreno->id,
                    'nome' => $lote->terreno->nome,
                ] : null,
                'data_colheita' => $lote->data_colheita?->toDateString(),
                'quantidade' => $lote->quantidade,
                'unidade' => $lote->unidade_medida,
                'localizacao_armazem' => $lote->localizacao_armazem,
                'observacoes' => $lote->observacoes,
            ])->values()),
        ];
    }
}
