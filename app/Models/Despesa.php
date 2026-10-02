<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\ContaCorrenteFornecedores;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends Model
{
    use SoftDeletes;

    protected $table = 'despesas';

    protected $fillable = [
        'titulo',
        'numero_fatura',
        'fornecedor',
        'fornecedor_id',
        'valor',
        'pago_no_ato',
        'data',
        'campanha_id',
        'maquina_id',
        'alfaia_id',
        'categoria',
        'ficheiro_path',
        'notas',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data' => 'date',
        'pago_no_ato' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Despesa $despesa) {
            // O texto do fornecedor vem como esta impresso na fatura; a ficha
            // e a mesma para "CASA QUERIDOS LDA" e "Casa Queridos".
            if ($despesa->isDirty('fornecedor') && ! $despesa->isDirty('fornecedor_id')) {
                $despesa->fornecedor_id = Fornecedor::paraNome($despesa->fornecedor, criar: true)?->id;
            }

            // Faturas-recibo e simplificadas ja vem pagas: nao sao divida.
            if ($despesa->isDirty('numero_fatura') && ! $despesa->isDirty('pago_no_ato')
                && self::numeroEhPagoNoAto($despesa->numero_fatura)) {
                $despesa->pago_no_ato = true;
            }
        });

        static::saved(function (Despesa $despesa) {
            if ($despesa->fornecedor_id && $despesa->numero_fatura
                && ($despesa->wasRecentlyCreated || $despesa->wasChanged(['fornecedor_id', 'numero_fatura']))) {
                app(ContaCorrenteFornecedores::class)->ligarPagamentosPendentes($despesa);
            }
        });
    }

    /**
     * FR (fatura-recibo), FS (fatura simplificada) e VD (venda a dinheiro)
     * sao pagas no momento da compra.
     */
    public static function numeroEhPagoNoAto(?string $numero): bool
    {
        return (bool) preg_match('/^\s*(FR|FS|VD)(?![A-Z])/i', (string) $numero);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaturaItem::class);
    }

    /** Ficha do fornecedor (a coluna `fornecedor` e o nome impresso na fatura). */
    public function fornecedorRegisto(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
    }

    /** Recibos que pagaram esta fatura, com o valor imputado em pivot->valor. */
    public function pagamentos(): BelongsToMany
    {
        return $this->belongsToMany(PagamentoFornecedor::class, 'pagamento_fornecedor_despesa', 'despesa_id', 'pagamento_fornecedor_id')
            ->withPivot('valor')
            ->withTimestamps();
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    /** Maquina a que a compra pertence (pecas, oleos, revisoes). */
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    /** Alfaia a que a compra pertence, quando nao e do tractor que a puxa. */
    public function alfaia(): BelongsTo
    {
        return $this->belongsTo(Alfaia::class);
    }

    /** Equipamento da despesa, para listas e titulos. */
    public function getEquipamentoNomeAttribute(): ?string
    {
        $nomes = array_filter([$this->maquina?->nome, $this->alfaia?->nome]);

        return $nomes === [] ? null : implode(' + ', $nomes);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoStock::class);
    }

    public function getSubtotalCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_sem_iva), 2);
        }

        return (float) $this->valor;
    }

    public function getIvaCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_iva_valor), 2);
        }

        return 0.0;
    }

    public function getTotalFaturaAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return round((float) $this->items->sum(fn ($i) => $i->total_com_iva), 2);
        }

        return (float) $this->valor;
    }
}
