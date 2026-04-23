<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cultura extends Model
{
    use SoftDeletes;

    protected $table = 'culturas';

    protected $fillable = [
        'parcela_id',
        'nome',
        'grupo_cultura',
        'ciclo_produtivo',
        'ano_inicio_producao',
        'data_fim_producao',
        'tipo',
        'variedade',
        'data_plantacao',
        'ciclo_dias',
        'previsao_colheita',
        'quantidade_esperada',
        'unidade_medida',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'data_plantacao' => 'date',
        'ano_inicio_producao' => 'integer',
        'data_fim_producao' => 'date',
        'previsao_colheita' => 'date',
        'quantidade_esperada' => 'decimal:2',
    ];

    // Relacionamentos
    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function campanhas(): HasMany
    {
        return $this->hasMany(Campanha::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    public function colheitas(): HasMany
    {
        return $this->hasMany(Colheita::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }
}
