<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Maquina extends Model
{
    use SoftDeletes;

    protected $table = 'maquinas';

    protected $fillable = [
        'nome',
        'tipo',
        'marca',
        'modelo',
        'matricula',
        'numero_serie',
        'ano_aquisicao',
        'horas_uso',
        'horas_manutencao',
        'consumo_agua_ha',
        'consumo_combustivel',
        'custo_hora',
        'custo_km',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'horas_uso' => 'decimal:2',
        'horas_manutencao' => 'decimal:2',
        'consumo_agua_ha' => 'decimal:2',
        'consumo_combustivel' => 'decimal:2',
        'custo_hora' => 'decimal:2',
        'custo_km' => 'decimal:2',
    ];

    // Relacionamentos
    public function alfaias(): HasMany
    {
        return $this->hasMany(Alfaia::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    public function manutencoes(): HasMany
    {
        return $this->hasMany(Manutencao::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }

    /** Linhas de utilizacao desta maquina em operacoes. */
    public function utilizacoes(): HasMany
    {
        return $this->hasMany(OperacaoRecurso::class);
    }

    /** Faturas de compra ligadas a maquina. */
    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    /** Pecas e reparacoes faturadas a esta maquina. Ver Alfaia::custo_pecas. */
    public function getCustoPecasAttribute(): float
    {
        return round((float) $this->despesas()->sum('valor'), 2);
    }

    public function getCustoManutencoesAttribute(): float
    {
        return round((float) $this->manutencoes()->sum('custo'), 2);
    }

    public function getCustoAcumuladoAttribute(): float
    {
        return round($this->custo_pecas + $this->custo_manutencoes, 2);
    }
}
