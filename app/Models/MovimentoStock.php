<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentoStock extends Model
{
    protected $table = 'movimento_stocks';

    protected $fillable = [
        'produto_id',
        'tipo',
        'quantidade',
        'unidade_medida',
        'custo_unitario',
        'referencia',
        'despesa_id',
        'fatura_item_id',
        'notas',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:3',
        'custo_unitario' => 'decimal:4',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function faturaItem(): BelongsTo
    {
        return $this->belongsTo(FaturaItem::class);
    }
}
