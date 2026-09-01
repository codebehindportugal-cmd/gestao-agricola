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

    /**
     * O tipo canonico usado pela aplicacao e 'fitofarmaco' - e o que a UI grava
     * e o que o Stock e o formulario de operacoes filtram. A API aceitava
     * 'fitofarmaceutico', pelo que a validacao DGAV passava ao lado de todos os
     * produtos criados pelo ecra.
     */
    public const TIPO_FITOFARMACO = 'fitofarmaco';

    public static function normalizarTipo(?string $tipo): ?string
    {
        $normalizado = strtr(mb_strtolower(trim((string) $tipo)), [
            'á' => 'a', 'â' => 'a', 'ã' => 'a', 'à' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);

        return match ($normalizado) {
            'fitofarmaceutico', 'fitofarmaco', 'produto fitofarmaceutico' => self::TIPO_FITOFARMACO,
            'fertilizacao', 'fertilizante', 'adubo', 'adubos' => 'fertilizante',
            'combustivel', 'combustiveis', 'gasoleo', 'diesel' => 'combustivel',
            'sementes', 'semente' => 'semente',
            'plantas', 'planta' => 'planta',
            '' => null,
            default => $normalizado,
        };
    }

    public function ehFitofarmaco(): bool
    {
        return self::normalizarTipo($this->tipo) === self::TIPO_FITOFARMACO;
    }

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
