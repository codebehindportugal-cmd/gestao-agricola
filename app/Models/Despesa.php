<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends Model
{
    use SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'titulo',
        'numero_fatura',
        'fornecedor',
        'valor',
        'data',
        'categoria',
        'ficheiro_path',
        'notas',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FaturaItem::class);
    }

    public function getSubtotalCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_sem_iva), 2);
        }

        return (float) $this->valor;
    }

    public function getIvaCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_iva_valor), 2);
        }

        return 0.0;
    }

    public function getTotalFaturaAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_com_iva), 2);
        }

        return (float) $this->valor;
    }
}
