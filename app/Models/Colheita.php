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
     * Custo da apanha que cabe a esta colheita.
     *
     * Uma apanha dura dias e passa por varios pomares, e cada pomar e uma
     * colheita. O custo da operacao (mao de obra, tratores, transporte)
     * reparte-se pelos quilos de cada colheita - a mesma regra do rateio da
     * luz das regas e do frio. Com uma colheita so, ela leva tudo.
     */
    public function getCustoApanhaAttribute(): float
    {
        return round($this->custo_total_apanha * $this->quota_apanha, 2);
    }

    /** O que a operacao de apanha custou, no total, antes de repartir. */
    public function getCustoTotalApanhaAttribute(): float
    {
        if (! $this->operacao) {
            return 0.0;
        }

        // O maior entre o custo_real da operacao e a soma dos Custos que lhe
        // estao ligados, nunca a soma dos dois: e o mesmo dinheiro escrito em
        // dois sitios. E a mesma regra que a Campanha usa.
        return round(max(
            (float) ($this->operacao->custo_real ?? 0),
            (float) $this->operacao->custos()->sum('valor')
        ), 2);
    }

    /**
     * Fracao do custo da apanha que pertence a esta colheita: os seus quilos
     * sobre os quilos todos que a operacao apanhou.
     *
     * Sem quilos registados em nenhuma das colheitas, reparte-se em partes
     * iguais - e arbitrario, mas melhor do que dar tudo a uma.
     */
    public function getQuotaApanhaAttribute(): float
    {
        if (! $this->operacao) {
            return 0.0;
        }

        $irmas = $this->colheitasDaMesmaApanha();

        if ($irmas->count() <= 1) {
            return 1.0;
        }

        $totalKg = (float) $irmas->sum(fn (Colheita $colheita) => (float) $colheita->quantidade_total);

        if ($totalKg <= 0) {
            return 1 / $irmas->count();
        }

        return (float) $this->quantidade_total / $totalKg;
    }

    /** Quilos apanhados por toda a operacao, nao so por esta colheita. */
    public function getQuantidadeApanhaAttribute(): float
    {
        return round((float) $this->colheitasDaMesmaApanha()
            ->sum(fn (Colheita $colheita) => (float) $colheita->quantidade_total), 2);
    }

    /**
     * As colheitas da mesma operacao, esta incluida. Usa a relacao ja carregada
     * quando existe, para a pagina da campanha nao fazer uma consulta por
     * colheita.
     *
     * @return \Illuminate\Support\Collection<int, Colheita>
     */
    public function colheitasDaMesmaApanha(): \Illuminate\Support\Collection
    {
        if (! $this->operacao) {
            return collect([$this]);
        }

        if ($this->operacao->relationLoaded('colheitas')) {
            return $this->operacao->colheitas;
        }

        return $this->operacao->colheitas()->get();
    }

    /** O que custou cada quilo apanhado nesta colheita. */
    public function getCustoPorKgAttribute(): float
    {
        $kg = (float) $this->quantidade_total;

        if ($kg <= 0) {
            return 0.0;
        }

        return round($this->custo_apanha / $kg, 4);
    }

    /**
     * Quanto veio de cada rubrica, para o ecra e o PDF de custos. Cada rubrica
     * leva a mesma fatia que o total.
     *
     * @return array{mao_obra: float, maquinas: float, outros: float, total: float}
     */
    public function detalheCustoApanha(): array
    {
        if (! $this->operacao) {
            return ['mao_obra' => 0.0, 'maquinas' => 0.0, 'outros' => 0.0, 'total' => 0.0];
        }

        $quota = $this->quota_apanha;

        $porTipo = $this->operacao->custos()
            ->selectRaw('tipo, SUM(valor) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $maoObra = round((float) $porTipo->get('mao_obra', 0) * $quota, 2);
        $maquinas = round((float) $porTipo->get('maquina', 0) * $quota, 2);
        $total = $this->custo_apanha;

        return [
            'mao_obra' => $maoObra,
            'maquinas' => $maquinas,
            'outros' => round($total - $maoObra - $maquinas, 2),
            'total' => $total,
        ];
    }
}
