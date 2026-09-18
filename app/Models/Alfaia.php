<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alfaia extends Model
{
    use SoftDeletes;

    protected $table = 'alfaias';

    protected $fillable = [
        'nome',
        'tipo',
        'maquina_id',
        'descricao',
        'comprimento',
        'largura',
        'consumo_agua_ha',
        'custo_hora',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'comprimento' => 'decimal:2',
        'largura' => 'decimal:2',
        'consumo_agua_ha' => 'decimal:2',
        'custo_hora' => 'decimal:2',
    ];

    // Relacionamentos
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    /** Linhas de utilizacao desta alfaia em operacoes. */
    public function utilizacoes(): HasMany
    {
        return $this->hasMany(OperacaoRecurso::class);
    }

    /** Revisoes e reparacoes proprias da alfaia. */
    public function manutencoes(): HasMany
    {
        return $this->hasMany(Manutencao::class);
    }

    /** Custos imputados a alfaia (pecas, reparacoes, utilizacao em operacoes). */
    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }

    /** Faturas de compra ligadas a alfaia. */
    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    /**
     * Pecas e reparacoes faturadas a esta alfaia.
     *
     * Conta-se pelas despesas e nao pelos custos: a fatura que entra pela API
     * cria os dois (despesa e custo) e a que e escrita no ecra so cria a
     * despesa - somar custos deixava metade de fora e somar ambos contava a
     * mesma fatura duas vezes.
     */
    public function getCustoPecasAttribute(): float
    {
        return round((float) $this->despesas()->sum('valor'), 2);
    }

    public function getCustoManutencoesAttribute(): float
    {
        return round((float) $this->manutencoes()->sum('custo'), 2);
    }

    /**
     * O que a alfaia ja custou em pecas e manutencao.
     *
     * Cuidado com a mesma reparacao registada duas vezes: a fatura da oficina
     * como despesa e o mesmo trabalho como manutencao com custo preenchido.
     * Sao somadas as duas - quando a revisao tem fatura, deixar a manutencao
     * sem custo.
     */
    public function getCustoAcumuladoAttribute(): float
    {
        return round($this->custo_pecas + $this->custo_manutencoes, 2);
    }
}
