<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'campanha_id',
        'maquina_id',
        'alfaia_id',
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

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    /** Maquina a que a compra pertence (pecas, oleos, revisoes). */
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    /** Alfaia a que a compra pertence, quando nao e do tractor que a puxa. */
    public function alfaia(): BelongsTo
    {
        return $this->belongsTo(Alfaia::class);
    }

    /** Equipamento da despesa, para listas e titulos. */
    public function getEquipamentoNomeAttribute(): ?string
    {
        $nomes = array_filter([$this->maquina?->nome, $this->alfaia?->nome]);

        return $nomes === [] ? null : implode(' + ', $nomes);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoStock::class);
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
