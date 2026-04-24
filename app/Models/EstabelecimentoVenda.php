<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstabelecimentoVenda extends Model
{
    protected $table = 'estabelecimentos_venda';

    protected $fillable = [
        'nome',
        'numero_autorizacao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'estabelecimento_venda_id');
    }
}
