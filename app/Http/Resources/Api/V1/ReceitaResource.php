<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descricao' => $this->descricao,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'data' => $this->data?->toDateString(),
            'comprador_nome' => $this->comprador_nome,
            'documento' => $this->documento,
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
            'colheita' => $this->whenLoaded('colheita', fn () => $this->colheita ? [
                'id' => $this->colheita->id,
                'data' => $this->colheita->data_colheita?->toDateString(),
                'referencia_externa' => $this->colheita->referencia_externa,
            ] : null),
            'lote' => $this->whenLoaded('lote', fn () => $this->lote ? [
                'id' => $this->lote->id,
                'codigo' => $this->lote->numero_lote,
            ] : null),
        ];
    }
}
