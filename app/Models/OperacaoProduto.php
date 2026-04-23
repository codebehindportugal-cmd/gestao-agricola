<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OperacaoProduto extends Pivot
{
    public $timestamps = true;

    protected $fillable = [
        'operacao_id',
        'produto_id',
        'quantidade',
        'unidade_medida',
        'dose',
        'dose_unidade',
        'area_tratada',
        'volume_calda',
        'finalidade',
        'intervalo_seguranca_dias',
        'estabelecimento_venda_nome',
        'estabelecimento_venda_autorizacao',
        'custo_unitario',
        'custo_total',
        'observacoes',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'dose' => 'decimal:3',
        'area_tratada' => 'decimal:2',
        'volume_calda' => 'decimal:2',
        'intervalo_seguranca_dias' => 'integer',
        'custo_unitario' => 'decimal:2',
        'custo_total' => 'decimal:2',
    ];

    // Relacionamentos
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
