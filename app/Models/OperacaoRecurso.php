<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um recurso usado numa operacao: uma maquina, uma alfaia, ou as duas juntas
 * (o trator com o empilhador de campo atrelado).
 *
 * O custo e calculado aqui e gravado em custo_total, para nao depender do
 * custo/hora que a maquina tiver no cadastro daqui a um ano.
 */
class OperacaoRecurso extends Model
{
    protected $table = 'operacao_recursos';

    protected $fillable = [
        'operacao_id',
        'maquina_id',
        'alfaia_id',
        'nome',
        'papel',
        'unidades',
        'horas',
        'km',
        'custo_hora',
        'custo_km',
        'custo_total',
        'observacoes',
    ];

    protected $casts = [
        'unidades' => 'integer',
        'horas' => 'decimal:2',
        'km' => 'decimal:2',
        'custo_hora' => 'decimal:2',
        'custo_km' => 'decimal:2',
        'custo_total' => 'decimal:2',
    ];

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function alfaia(): BelongsTo
    {
        return $this->belongsTo(Alfaia::class);
    }

    /**
     * Custo desta linha: horas x custo/hora mais km x custo/km, vezes o numero
     * de unidades iguais. Se nenhum dos dois estiver preenchido da zero - e
     * preferivel a inventar um valor.
     */
    public function calcularCusto(): float
    {
        $unidades = max(1, (int) $this->unidades);

        $porHoras = (float) ($this->horas ?? 0) * (float) ($this->custo_hora ?? 0);
        $porKm = (float) ($this->km ?? 0) * (float) ($this->custo_km ?? 0);

        return round(($porHoras + $porKm) * $unidades, 2);
    }

    /** Nome legivel da linha, para descricoes de custo e para os ecras. */
    public function getDescricaoAttribute(): string
    {
        $partes = array_filter([
            $this->maquina?->nome,
            $this->alfaia?->nome,
        ]);

        $nome = $partes === []
            ? ($this->nome ?: 'recurso')
            : implode(' + ', $partes);

        if ((int) $this->unidades > 1) {
            $nome = $this->unidades.'x '.$nome;
        }

        return $this->papel ? $nome.' ('.$this->papel.')' : $nome;
    }
}
