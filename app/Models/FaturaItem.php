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
        'desconto_percentagem',
        'iva_percentagem',
        'produto_id',
        'notas',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'preco_unitario' => 'decimal:4',
        'desconto_percentagem' => 'decimal:2',
        'iva_percentagem' => 'decimal:2',
    ];

    protected $appends = ['total_bruto', 'desconto_valor', 'preco_liquido', 'total_sem_iva', 'total_iva_valor', 'total_com_iva'];

    /** Quantidade vezes preco de tabela, antes do desconto. */
    public function getTotalBrutoAttribute(): float
    {
        return round((float) $this->quantidade * (float) $this->preco_unitario, 4);
    }

    public function getDescontoValorAttribute(): float
    {
        return round($this->total_bruto * (float) $this->desconto_percentagem / 100, 4);
    }

    /** O que o produto custou mesmo, por unidade: e este o preco que vai para stock. */
    public function getPrecoLiquidoAttribute(): float
    {
        return round((float) $this->preco_unitario * (1 - (float) $this->desconto_percentagem / 100), 4);
    }

    public function getTotalSemIvaAttribute(): float
    {
        return round($this->total_bruto - $this->desconto_valor, 4);
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
