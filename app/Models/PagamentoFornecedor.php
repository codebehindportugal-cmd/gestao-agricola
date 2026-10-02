<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um recibo do fornecedor: quanto se pagou, quando, e que faturas liquidou.
 *
 * O valor que nao fica ligado a nenhuma fatura (adiantamento, ou fatura que
 * ainda nao foi registada) continua a contar no saldo do fornecedor; aparece
 * como "por imputar".
 */
class PagamentoFornecedor extends Model
{
    use SoftDeletes;

    protected $table = 'pagamentos_fornecedores';

    public const METODOS = ['transferencia', 'numerario', 'cheque', 'multibanco', 'debito_direto', 'outro'];

    protected $fillable = [
        'fornecedor_id',
        'data',
        'valor',
        'numero_recibo',
        'metodo',
        'ficheiro_path',
        'faturas_pendentes',
        'notas',
        'referencia_externa',
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
        'faturas_pendentes' => 'array',
    ];

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    /** Faturas que este recibo pagou, com o valor imputado a cada uma em pivot->valor. */
    public function despesas(): BelongsToMany
    {
        return $this->belongsToMany(Despesa::class, 'pagamento_fornecedor_despesa', 'pagamento_fornecedor_id', 'despesa_id')
            ->withPivot('valor')
            ->withTimestamps();
    }

    public function getValorImputadoAttribute(): float
    {
        return round((float) $this->despesas()->sum('pagamento_fornecedor_despesa.valor'), 2);
    }

    public function getValorPorImputarAttribute(): float
    {
        return round(max(0, (float) $this->valor - $this->valor_imputado), 2);
    }
}
