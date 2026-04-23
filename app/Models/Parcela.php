<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parcela extends Model
{
    use SoftDeletes;

    protected $table = 'parcelas';

    protected $fillable = [
        'terreno_id',
        'nome',
        'numero_parcela',
        'area_total',
        'area_util',
        'descricao',
        'estado',
        'tipo_ocupacao',
        'numero_arvores',
        'compasso_linha_m',
        'compasso_planta_m',
        'latitude',
        'longitude',
        'poligono',
    ];

    protected $casts = [
        'area_total' => 'decimal:2',
        'area_util' => 'decimal:2',
        'numero_arvores' => 'integer',
        'compasso_linha_m' => 'decimal:2',
        'compasso_planta_m' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'poligono' => 'array',
    ];

    // Relacionamentos
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    public function culturas(): HasMany
    {
        return $this->hasMany(Cultura::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    public function colheitas(): HasMany
    {
        return $this->hasMany(Colheita::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }
}
