<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends Model
{
    use SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'titulo',
        'numero_fatura',
        'fornecedor',
        'valor',
        'data',
        'categoria',
        'ficheiro_path',
        'notas',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data' => 'date',
    ];
}
