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

    // Relacionamentos
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

    // Método para calcular custo por kg de produção
    public function getCustoPorKgAttribute()
    {
        $totalKg = $this->colheitas->sum('quantidade');

        if ($totalKg <= 0) {
            return 0;
        }

        $totalCusto = $this->operacoes->sum('custo_real') + $this->custos->sum('valor');

        return round($totalCusto / $totalKg, 2);
    }
}
