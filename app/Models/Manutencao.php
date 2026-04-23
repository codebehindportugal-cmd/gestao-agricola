<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manutencao extends Model
{
    use SoftDeletes;

    protected $table = 'manutencoes';

    protected $fillable = [
        'maquina_id',
        'data_manutencao',
        'tipo',
        'descricao',
        'custo',
        'duracao_minutos',
        'proxima_manutencao',
        'observacoes',
    ];

    protected $casts = [
        'data_manutencao' => 'date',
        'proxima_manutencao' => 'date',
        'custo' => 'decimal:2',
    ];

    // Relacionamentos
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }
}
