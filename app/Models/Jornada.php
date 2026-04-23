<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jornada extends Model
{
    use SoftDeletes;

    protected $table = 'jornadas';

    protected $fillable = [
        'funcionario_id',
        'operacao_id',
        'data',
        'horas_trabalhadas',
        'tarefa',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'date',
        'horas_trabalhadas' => 'decimal:2',
    ];

    // Relacionamentos
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }
}
