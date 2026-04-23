<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Terreno extends Model
{
    use SoftDeletes;

    protected $table = 'terrenos';

    protected $fillable = [
        'nome',
        'descricao',
        'area_total',
        'tipo_solo',
        'localizacao',
        'latitude',
        'longitude',
        'poligono',
        'estado',
    ];

    protected $casts = [
        'area_total' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'poligono' => 'array',
    ];

    // Relacionamentos
    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }
}
