<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campanha extends Model
{
    use SoftDeletes;

    protected $table = 'campanhas';

    protected $fillable = [
        'cultura_id',
        'ano',
        'data_inicio',
        'data_fim',
        'status',
        'producao_esperada',
        'producao_real',
        'custo_estimado',
        'custo_real',
        'observacoes',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'producao_esperada' => 'decimal:2',
        'producao_real' => 'decimal:2',
        'custo_estimado' => 'decimal:2',
        'custo_real' => 'decimal:2',
    ];

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function colheitas(): HasMany
    {
        return $this->hasMany(Colheita::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }

    public function getCustoPorKgAttribute(): float
    {
        $totalKg = (float) $this->colheitas->sum('quantidade_total');

        if ($totalKg <= 0) {
            return 0;
        }

        $totalCusto = $this->custo_total_calculado;

        return round($totalCusto / $totalKg, 2);
    }

    public function getCustoProdutosAttribute(): float
    {
        return (float) $this->operacoes
            ->flatMap(fn (Operacao $operacao) => $operacao->produtos)
            ->sum(function (Produto $produto) {
                if ($produto->pivot?->custo_total !== null) {
                    return (float) $produto->pivot->custo_total;
                }

                if ($produto->pivot?->custo_unitario === null) {
                    return 0;
                }

                return round((float) ($produto->pivot->quantidade ?? 0) * (float) $produto->pivot->custo_unitario, 2);
            });
    }

    public function getCustoTotalCalculadoAttribute(): float
    {
        return round(
            (float) $this->operacoes->sum('custo_real')
            + $this->custo_produtos
            + (float) $this->custos->sum('valor'),
            2
        );
    }
}
