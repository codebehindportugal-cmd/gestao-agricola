<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Colheita extends Model
{
    use SoftDeletes;

    protected $table = 'colheitas';

    protected $fillable = [
        'cultura_id',
        'operacao_id',
        'campanha_id',
        'parcela_id',
        'data_colheita',
        'quantidade_total',
        'unidade_medida',
        'qualidade',
        'quantidade_perdas',
        'motivo_perdas',
        'operador_id',
        'referencia_externa',
        'observacoes',
    ];

    protected $casts = [
        'data_colheita' => 'date',
        'quantidade_total' => 'decimal:2',
        'quantidade_perdas' => 'decimal:2',
    ];

    // Relacionamentos
    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * Custo da apanha: tudo o que a operacao ligada a esta colheita custou -
     * mao de obra, maquinas e transporte.
     *
     * Conta-se o maior entre o custo_real da operacao e a soma dos Custos que
     * lhe estao ligados, nunca a soma dos dois: sao o mesmo dinheiro escrito
     * em dois sitios. E a mesma regra que a Campanha usa.
     */
    public function getCustoApanhaAttribute(): float
    {
        if (! $this->operacao) {
            return 0.0;
        }

        return round(max(
            (float) ($this->operacao->custo_real ?? 0),
            (float) $this->operacao->custos()->sum('valor')
        ), 2);
    }

    /** O que custou cada quilo apanhado. Zero sem operacao ligada ou sem quilos. */
    public function getCustoPorKgAttribute(): float
    {
        $kg = (float) $this->quantidade_total;

        if ($kg <= 0) {
            return 0.0;
        }

        return round($this->custo_apanha / $kg, 4);
    }

    /**
     * Quanto veio de cada rubrica, para o ecra e o PDF de custos.
     *
     * @return array{mao_obra: float, maquinas: float, outros: float, total: float}
     */
    public function detalheCustoApanha(): array
    {
        if (! $this->operacao) {
            return ['mao_obra' => 0.0, 'maquinas' => 0.0, 'outros' => 0.0, 'total' => 0.0];
        }

        $porTipo = $this->operacao->custos()
            ->selectRaw('tipo, SUM(valor) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $maoObra = round((float) $porTipo->get('mao_obra', 0), 2);
        $maquinas = round((float) $porTipo->get('maquina', 0), 2);
        $outros = round((float) $porTipo->sum() - $maoObra - $maquinas, 2);

        return [
            'mao_obra' => $maoObra,
            'maquinas' => $maquinas,
            'outros' => $outros,
            'total' => $this->custo_apanha,
        ];
    }
}
