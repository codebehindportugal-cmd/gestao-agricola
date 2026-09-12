<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alfaia extends Model
{
    use SoftDeletes;

    protected $table = 'alfaias';

    protected $fillable = [
        'nome',
        'tipo',
        'maquina_id',
        'descricao',
        'comprimento',
        'largura',
        'consumo_agua_ha',
        'custo_hora',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'comprimento' => 'decimal:2',
        'largura' => 'decimal:2',
        'consumo_agua_ha' => 'decimal:2',
        'custo_hora' => 'decimal:2',
    ];

    // Relacionamentos
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class);
    }

    /** Linhas de utilizacao desta alfaia em operacoes. */
    public function utilizacoes(): HasMany
    {
        return $this->hasMany(OperacaoRecurso::class);
    }
}
