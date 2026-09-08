<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\RateioCustosService;

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

    /**
     * Custo das operacoes sem duplicar os custos que ja lhes estao ligados.
     *
     * Um Custo com operacao_id representa a mesma despesa que a operacao ja
     * regista em custo_real - e o que a /api/v1/trabalhos grava para a mao de
     * obra - por isso conta-se o maior dos dois e nunca a soma. Se a operacao
     * nao tiver custo_real, valem os custos ligados; se nao tiver custos
     * ligados, vale o custo_real.
     */
    public function getCustoOperacoesAttribute(): float
    {
        return round((float) $this->operacoes->sum(
            fn (Operacao $operacao) => $this->custoEfetivoOperacao($operacao)
        ), 2);
    }

    /** Custo proprio de uma operacao, sem duplicar os custos que lhe estao ligados. */
    public function custoEfetivoOperacao(Operacao $operacao): float
    {
        return round(max(
            (float) ($operacao->custo_real ?? 0),
            (float) $this->custosLigadosPorOperacao()->get($operacao->id, 0)
        ), 2);
    }

    /** Custos que nao pertencem a nenhuma operacao desta campanha (faturas, IMI, seguros). */
    public function getCustoDiretosAttribute(): float
    {
        return round((float) $this->custosAvulsos()->sum('valor'), 2);
    }

    /** @return \Illuminate\Support\Collection<int,float> valor total por operacao_id */
    private function custosLigadosPorOperacao(): \Illuminate\Support\Collection
    {
        return $this->custos
            ->filter(fn (Custo $custo) => $custo->operacao_id !== null)
            ->groupBy('operacao_id')
            ->map(fn ($grupo) => (float) $grupo->sum('valor'));
    }

    /** Custos desta campanha que nao estao ligados a nenhuma das suas operacoes. */
    public function custosAvulsos(): \Illuminate\Support\Collection
    {
        $idsOperacoes = $this->operacoes->pluck('id')->all();

        return $this->custos->filter(
            fn (Custo $custo) => $custo->operacao_id === null
                || ! in_array($custo->operacao_id, $idsOperacoes)
        )->values();
    }

    /**
     * Parte dos custos partilhados (luz das regas, frio, IMI, seguros) que
     * pertence a esta campanha. Ver RateioCustosService.
     */
    public function getCustoRateadoAttribute(): float
    {
        return app(RateioCustosService::class)->totalPara($this);
    }

    /** Linhas do rateio, para o ecra da campanha e o PDF de custos. */
    public function detalheRateio(): array
    {
        return app(RateioCustosService::class)->detalhePara($this);
    }

    public function getCustoTotalCalculadoAttribute(): float
    {
        return round(
            $this->custo_operacoes
            + $this->custo_produtos
            + $this->custo_diretos
            + $this->custo_rateado,
            2
        );
    }

    /** Quilos colhidos na campanha; o denominador do custo/kg e do preco medio. */
    public function getProducaoTotalKgAttribute(): float
    {
        return round((float) $this->colheitas->sum('quantidade_total') ?: (float) ($this->producao_real ?? 0), 2);
    }

    /** Quilos vendidos, somados das receitas com quantidade registada. */
    public function getQuantidadeVendidaAttribute(): float
    {
        return round((float) $this->receitas()->sum('quantidade'), 2);
    }

    /** Preco medio de venda por quilo, so das vendas que registam quantidade. */
    public function getPrecoMedioVendaAttribute(): float
    {
        $linhas = $this->receitas()->whereNotNull('quantidade')->where('quantidade', '>', 0);

        $kg = (float) (clone $linhas)->sum('quantidade');

        if ($kg <= 0) {
            return 0;
        }

        return round((float) $linhas->sum('valor') / $kg, 4);
    }

    /** Receitas menos custos: o que a campanha deixou. */
    public function getMargemAttribute(): float
    {
        return round($this->receita_total - $this->custo_total_calculado, 2);
    }
}
