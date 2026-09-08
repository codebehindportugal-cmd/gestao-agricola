<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receita extends Model
{
    protected $table = 'receitas';

    protected $fillable = [
        'descricao',
        'tipo',
        'valor',
        'quantidade',
        'unidade',
        'preco_unitario',
        'data',
        'campanha_id',
        'cultura_id',
        'parcela_id',
        'colheita_id',
        'lote_id',
        'comprador_nome',
        'documento',
        'referencia_externa',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
        'quantidade' => 'decimal:3',
        'preco_unitario' => 'decimal:4',
    ];

    /** Preco por unidade: o registado, ou o que resulta do valor a dividir pela quantidade. */
    public function getPrecoEfetivoAttribute(): ?float
    {
        if ($this->preco_unitario !== null) {
            return (float) $this->preco_unitario;
        }

        $quantidade = (float) $this->quantidade;

        return $quantidade > 0 ? round((float) $this->valor / $quantidade, 4) : null;
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function colheita(): BelongsTo
    {
        return $this->belongsTo(Colheita::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
