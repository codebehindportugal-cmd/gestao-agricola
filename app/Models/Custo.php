<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Custo extends Model
{
    use SoftDeletes;

    protected $table = 'custos';

    protected $fillable = [
        'descricao',
        'tipo',
        'valor',
        'rateavel',
        'base_rateio',
        'data_custo',
        'operacao_id',
        'campanha_id',
        'cultura_id',
        'parcela_id',
        'maquina_id',
        'funcionario_id',
        'referencia_externa',
        'observacoes',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'rateavel' => 'boolean',
        'data_custo' => 'date',
    ];

    /** Bases de rateio aceites para custos partilhados. */
    public const BASES_RATEIO = ['kg', 'area'];

    /** Custos partilhados por varias campanhas (luz da rega, frio, IMI, seguros). */
    public function scopePartilhados($query)
    {
        return $query->where('rateavel', true)->whereNull('campanha_id');
    }

    // Relacionamentos
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}
