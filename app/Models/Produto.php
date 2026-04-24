<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'nome',
        'tipo',
        'codigo_interno',
        'numero_autorizacao_dgav',
        'estabelecimento_venda_nome',
        'estabelecimento_venda_autorizacao',
        'estabelecimento_venda_id',
        'fornecedor_id',
        'custo_unitario',
        'unidade_medida',
        'stock_minimo',
        'descricao',
        'data_validade',
        'observacoes',
    ];

    protected $casts = [
        'custo_unitario' => 'decimal:2',
        'data_validade' => 'date',
    ];

    // Relacionamentos
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function operacoes(): BelongsToMany
    {
        return $this->belongsToMany(Operacao::class, 'operacao_produtos')
            ->withPivot(
                'quantidade',
                'unidade_medida',
                'dose',
                'dose_unidade',
                'area_tratada',
                'volume_calda',
                'finalidade',
                'intervalo_seguranca_dias',
                'estabelecimento_venda_nome',
                'estabelecimento_venda_autorizacao',
                'custo_unitario',
                'custo_total',
                'observacoes',
            )
            ->withTimestamps();
    }

    public function estabelecimentoVenda(): BelongsTo
    {
        return $this->belongsTo(EstabelecimentoVenda::class, 'estabelecimento_venda_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
