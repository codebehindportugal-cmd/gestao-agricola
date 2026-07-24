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
    ];

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
