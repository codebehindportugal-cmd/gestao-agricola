<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lote extends Model
{
    use SoftDeletes;

    protected $table = 'lotes';

    protected $fillable = [
        'colheita_id',
        'armazem_id',
        'numero_lote',
        'quantidade',
        'unidade_medida',
        'data_entrada',
        'data_saida',
        'localizacao_armazem',
        'qualidade',
        'status',
        'rastreabilidade',
        'observacoes',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'data_entrada' => 'date',
        'data_saida' => 'date',
        'rastreabilidade' => 'json',
    ];

    // Relacionamentos
    public function colheita(): BelongsTo
    {
        return $this->belongsTo(Colheita::class);
    }

    public function armazem(): BelongsTo
    {
        return $this->belongsTo(Armazem::class);
    }
}
