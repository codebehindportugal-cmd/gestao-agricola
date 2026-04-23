<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Colheita extends Model
{
    use SoftDeletes;

    protected $table = 'colheitas';

    protected $fillable = [
        'cultura_id',
        'campanha_id',
        'parcela_id',
        'data_colheita',
        'quantidade_total',
        'unidade_medida',
        'qualidade',
        'quantidade_perdas',
        'motivo_perdas',
        'operador_id',
        'observacoes',
    ];

    protected $casts = [
        'data_colheita' => 'date',
        'quantidade_total' => 'decimal:2',
        'quantidade_perdas' => 'decimal:2',
    ];

    // Relacionamentos
    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
