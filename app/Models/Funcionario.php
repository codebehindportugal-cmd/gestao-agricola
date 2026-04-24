<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Funcionario extends Model
{
    use SoftDeletes;

    protected $table = 'funcionarios';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'cargo',
        'aplicador_numero_autorizacao',
        'data_admissao',
        'data_saida',
        'tipo_contrato',
        'valor_hora',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'data_admissao' => 'date',
        'data_saida' => 'date',
        'valor_hora' => 'decimal:2',
    ];

    // Relacionamentos
    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class);
    }

    public function equipas(): BelongsToMany
    {
        return $this->belongsToMany(Equipa::class, 'equipa_funcionario')
            ->withTimestamps();
    }

    public function equipasLideradas(): HasMany
    {
        return $this->hasMany(Equipa::class, 'lider_id');
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }
}
