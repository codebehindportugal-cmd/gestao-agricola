<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaItem extends Model
{
    protected $table = 'fatura_items';

    protected $fillable = [
        'despesa_id',
        'descricao',
        'quantidade',
        'conteudo_embalagem',
        'unidade_embalagem',
        'preco_unitario',
        'desconto_percentagem',
        'iva_percentagem',
        'produto_id',
        'notas',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'conteudo_embalagem' => 'decimal:4',
        'preco_unitario' => 'decimal:4',
        'desconto_percentagem' => 'decimal:2',
        'iva_percentagem' => 'decimal:2',
    ];

    protected $appends = ['total_bruto', 'desconto_valor', 'preco_liquido', 'total_sem_iva', 'total_iva_valor', 'total_com_iva'];

    /** Quantidade vezes preco de tabela, antes do desconto. */
    public function getTotalBrutoAttribute(): float
    {
        return round((float) $this->quantidade * (float) $this->preco_unitario, 4);
    }

    public function getDescontoValorAttribute(): float
    {
        return round($this->total_bruto * (float) $this->desconto_percentagem / 100, 4);
    }

    /**
     * Quanto leva a embalagem desta linha.
     *
     * A linha ganha ao catalogo: o mesmo produto vende-se em 5 L e em 20 L, e o
     * `conteudo` do produto e so o tamanho habitual. Sem nenhum dos dois vale
     * 1 — a compra conta unidades, como antes desta coluna existir.
     */
    public function getConteudoEfetivoAttribute(): float
    {
        $daLinha = (float) ($this->attributes['conteudo_embalagem'] ?? 0);

        if ($daLinha > 0) {
            return $daLinha;
        }

        return $this->produto?->conteudo_por_embalagem ?? 1.0;
    }

    /** Quanto entrou em stock: embalagens vezes o que cada uma leva. */
    public function getQuantidadeBaseAttribute(): float
    {
        return round((float) $this->quantidade * $this->conteudo_efetivo, 3);
    }

    /** O que custou cada litro ou quilo, nao cada embalagem. */
    public function getCustoPorUnidadeBaseAttribute(): float
    {
        return round($this->preco_liquido / $this->conteudo_efetivo, 4);
    }

    /** O que o produto custou mesmo, por unidade: e este o preco que vai para stock. */
    public function getPrecoLiquidoAttribute(): float
    {
        return round((float) $this->preco_unitario * (1 - (float) $this->desconto_percentagem / 100), 4);
    }

    public function getTotalSemIvaAttribute(): float
    {
        return round($this->total_bruto - $this->desconto_valor, 4);
    }

    public function getTotalIvaValorAttribute(): float
    {
        return round($this->total_sem_iva * (float) $this->iva_percentagem / 100, 4);
    }

    public function getTotalComIvaAttribute(): float
    {
        return round($this->total_sem_iva + $this->total_iva_valor, 2);
    }

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
