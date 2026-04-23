<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'produto_id',
        'armazem_id',
        'quantidade',
        'unidade_medida',
        'data_atualizado',
        'observacoes',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'data_atualizado' => 'date',
    ];

    // Relacionamentos
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function armazem(): BelongsTo
    {
        return $this->belongsTo(Armazem::class);
    }
}
