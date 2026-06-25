<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaItem extends Model
{
    protected $table = 'fatura_items';

    protected $fillable = [
        'despesa_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'iva_percentagem',
        'produto_id',
        'notas',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'preco_unitario' => 'decimal:4',
        'iva_percentagem' => 'decimal:2',
    ];

    protected $appends = ['total_sem_iva', 'total_iva_valor', 'total_com_iva'];

    public function getTotalSemIvaAttribute(): float
    {
        return round((float) $this->quantidade * (float) $this->preco_unitario, 4);
    }

    public function getTotalIvaValorAttribute(): float
    {
        return round($this->total_sem_iva * (float) $this->iva_percentagem / 100, 4);
    }

    public function getTotalComIvaAttribute(): float
    {
        return round($this->total_sem_iva + $this->total_iva_valor, 2);
    }

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
