<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use SoftDeletes;

    protected $table = 'fornecedores';

    protected $fillable = [
        'nome',
        'contacto',
        'email',
        'telefone',
        'localizacao',
        'nif',
        'observacoes',
        'status',
    ];

    // Relacionamentos
    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }
}
