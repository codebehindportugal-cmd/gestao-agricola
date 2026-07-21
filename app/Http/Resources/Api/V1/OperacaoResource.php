<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'data' => $this->data_hora_inicio?->toDateString(),
            'data_hora_inicio' => $this->data_hora_inicio?->toISOString(),
            'estado' => $this->estado,
            'produtor_nome' => $this->produtor_nome,
            'aplicador_nome' => $this->aplicador_nome,
            'aplicador_numero_autorizacao' => $this->aplicador_numero_autorizacao,
            'exploracao_concelho' => $this->exploracao_concelho,
            'exploracao_freguesia' => $this->exploracao_freguesia,
            'custo_estimado' => $this->custo_estimado,
            'custo_real' => $this->custo_real,
            'referencia_externa' => $this->referencia_externa,
            'observacoes' => $this->observacoes,
            'campanha' => $this->whenLoaded('campanha', fn () => $this->campanha ? [
                'id' => $this->campanha->id,
                'ano' => $this->campanha->ano,
            ] : null),
            'parcela' => $this->whenLoaded('parcela', fn () => $this->parcela ? [
                'id' => $this->parcela->id,
                'nome' => $this->parcela->nome,
            ] : null),
            'cultura' => $this->whenLoaded('cultura', fn () => $this->cultura ? [
                'id' => $this->cultura->id,
                'nome' => $this->cultura->nome,
            ] : null),
            'produtos' => $this->whenLoaded('produtos', fn () => $this->produtos->map(fn ($produto) => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'tipo' => $produto->tipo,
                'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                'quantidade' => $produto->pivot->quantidade,
                'unidade_medida' => $produto->pivot->unidade_medida,
                'dose' => $produto->pivot->dose,
                'dose_unidade' => $produto->pivot->dose_unidade,
                'area_tratada' => $produto->pivot->area_tratada,
                'volume_calda' => $produto->pivot->volume_calda,
                'finalidade' => $produto->pivot->finalidade,
                'intervalo_seguranca_dias' => $produto->pivot->intervalo_seguranca_dias,
                'estabelecimento_venda_nome' => $produto->pivot->estabelecimento_venda_nome,
                'estabelecimento_venda_autorizacao' => $produto->pivot->estabelecimento_venda_autorizacao,
                'custo_unitario' => $produto->pivot->custo_unitario,
                'custo_total' => $produto->pivot->custo_total,
                'observacoes' => $produto->pivot->observacoes,
            ])->values()),
        ];
    }
}
