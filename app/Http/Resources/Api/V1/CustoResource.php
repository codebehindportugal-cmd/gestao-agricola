<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descricao' => $this->descricao,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'data' => $this->data_custo?->toDateString(),
            'referencia_externa' => $this->referencia_externa,
            'observacoes' => $this->observacoes,
            'campanha' => $this->whenLoaded('campanha', fn () => $this->campanha ? [
                'id' => $this->campanha->id,
                'ano' => $this->campanha->ano,
            ] : null),
            'operacao' => $this->whenLoaded('operacao', fn () => $this->operacao ? [
                'id' => $this->operacao->id,
                'tipo' => $this->operacao->tipo,
            ] : null),
            'cultura' => $this->whenLoaded('cultura', fn () => $this->cultura ? [
                'id' => $this->cultura->id,
                'nome' => $this->cultura->nome,
            ] : null),
            'parcela' => $this->whenLoaded('parcela', fn () => $this->parcela ? [
                'id' => $this->parcela->id,
                'nome' => $this->parcela->nome,
            ] : null),
            'maquina' => $this->whenLoaded('maquina', fn () => $this->maquina ? [
                'id' => $this->maquina->id,
                'nome' => $this->maquina->nome,
            ] : null),
            'funcionario' => $this->whenLoaded('funcionario', fn () => $this->funcionario ? [
                'id' => $this->funcionario->id,
                'nome' => $this->funcionario->nome,
            ] : null),
        ];
    }
}
