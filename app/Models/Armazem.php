<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Armazem extends Model
{
    use SoftDeletes;

    protected $table = 'armazens';

    protected $fillable = [
        'nome',
        'tipo',
        'localizacao',
        'capacidade',
        'area',
        'observacoes',
        'status',
    ];

    protected $casts = [
        'capacidade' => 'decimal:2',
        'area' => 'decimal:2',
    ];

    // Relacionamentos
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
