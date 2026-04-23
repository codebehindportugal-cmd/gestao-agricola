<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipa extends Model
{
    use SoftDeletes;

    protected $table = 'equipas';

    protected $fillable = [
        'nome',
        'lider_id',
        'descricao',
        'status',
    ];

    // Relacionamentos
    public function lider(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'lider_id');
    }

    public function funcionarios(): BelongsToMany
    {
        return $this->belongsToMany(Funcionario::class, 'equipa_funcionario')
            ->withTimestamps();
    }
}
