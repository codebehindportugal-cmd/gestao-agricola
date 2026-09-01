<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campanha extends Model
{
    use SoftDeletes;

    protected $table = 'campanhas';

    protected $fillable = [
        'nome',
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

    /** Parcelas cobertas por uma campanha geral. */
    public function parcelas(): BelongsToMany
    {
        return $this->belongsToMany(Parcela::class, 'campanha_parcela')->withTimestamps();
    }

    public function ehGeral(): bool
    {
        return $this->cultura_id === null;
    }

    /**
     * As parcelas que a campanha realmente cobre: as ligadas explicitamente
     * numa campanha geral, ou a unica parcela da cultura numa campanha antiga.
     */
    public function parcelasEfetivas(): \Illuminate\Support\Collection
    {
        if ($this->parcelas->isNotEmpty()) {
            return $this->parcelas;
        }

        $parcela = $this->cultura?->parcela;

        return $parcela ? collect([$parcela]) : collect();
    }

    public function getNomeCompletoAttribute(): string
    {
        if (filled($this->nome)) {
            return $this->nome;
        }

        return trim(($this->cultura?->nome ? $this->cultura->nome.' ' : '').$this->ano);
    }

    /** Area da campanha: a soma das parcelas que cobre. */
    public function getAreaTotalHaAttribute(): float
    {
        return round((float) $this->parcelasEfetivas()->sum(
            fn (Parcela $parcela) => (float) ($parcela->area_util ?: $parcela->area_total ?: 0)
        ), 4);
    }

    /** Nomes dos terrenos abrangidos, para o caderno de campo. */
    public function getExploracaoNomeAttribute(): string
    {
        $nomes = $this->parcelasEfetivas()
            ->map(fn (Parcela $parcela) => $parcela->terreno?->nome)
            ->filter()
            ->unique()
            ->values();

        return $nomes->isEmpty() ? 'N/A' : $nomes->implode(', ');
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

    public function receitas(): HasMany
    {
        return $this->hasMany(Receita::class);
    }

    public function getReceitaTotalAttribute(): float
    {
        return round((float) $this->receitas()->sum('valor'), 2);
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
