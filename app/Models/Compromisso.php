<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compromisso extends Model
{
    use SoftDeletes;

    public const CATEGORIAS = [
        'pagamento',
        'tarefa_agricola',
        'manutencao',
        'prazo_legal',
    ];

    public const ESTADOS = [
        'pendente',
        'concluido',
        'cancelado',
    ];

    public const RECORRENCIAS = [
        'nenhuma',
        'mensal',
        'trimestral',
        'semestral',
        'anual',
        'personalizada',
    ];

    public const UNIDADES_RECORRENCIA = [
        'dia',
        'semana',
        'mes',
        'ano',
    ];

    /** categoria -> tipo de Custo criado ao marcar como concluido */
    public const CATEGORIA_PARA_TIPO_CUSTO = [
        'pagamento' => 'outro',
        'tarefa_agricola' => 'mao_obra',
        'manutencao' => 'manutencao',
        'prazo_legal' => 'outro',
    ];

    protected $table = 'compromissos';

    /**
     * Os defaults da migracao so existem na base de dados: um modelo acabado de
     * criar sem estes campos ficava com null em memoria, o que devolvia
     * estado=null na API e fazia o gerador inserir null numa coluna NOT NULL.
     */
    protected $attributes = [
        'categoria' => 'pagamento',
        'estado' => 'pendente',
        'recorrencia' => 'nenhuma',
        'antecedencia_aviso_dias' => 7,
    ];

    protected $fillable = [
        'titulo',
        'descricao',
        'categoria',
        'tipo',
        'entidade',
        'data',
        'hora',
        'valor',
        'estado',
        'data_conclusao',
        'valor_pago',
        'recorrencia',
        'recorrencia_intervalo',
        'recorrencia_unidade',
        'recorrencia_fim',
        'compromisso_pai_id',
        'antecedencia_aviso_dias',
        'campanha_id',
        'parcela_id',
        'cultura_id',
        'maquina_id',
        'funcionario_id',
        'custo_id',
        'referencia_externa',
        'notas',
    ];

    protected $casts = [
        'data' => 'date',
        'data_conclusao' => 'date',
        'recorrencia_fim' => 'date',
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
    ];

    protected $appends = ['dias_para_prazo', 'atrasado'];

    // ── Relacionamentos ──────────────────────────────────────────────────────

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compromisso_pai_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(self::class, 'compromisso_pai_id');
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function custo(): BelongsTo
    {
        return $this->belongsTo(Custo::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('estado', 'pendente');
    }

    public function scopeAtrasados(Builder $query): Builder
    {
        return $query->where('estado', 'pendente')->whereDate('data', '<', now()->toDateString());
    }

    public function scopeEntre(Builder $query, string $de, string $ate): Builder
    {
        return $query->whereBetween('data', [$de, $ate]);
    }

    /** Series-mae: as que geram ocorrencias futuras. */
    public function scopeSeries(Builder $query): Builder
    {
        return $query->where('recorrencia', '!=', 'nenhuma')->whereNull('compromisso_pai_id');
    }

    // ── Atributos calculados ────────────────────────────────────────────────

    public function getDiasParaPrazoAttribute(): ?int
    {
        if ($this->data === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->data->startOfDay(), false);
    }

    public function getAtrasadoAttribute(): bool
    {
        return $this->estado === 'pendente'
            && $this->data !== null
            && $this->data->startOfDay()->lessThan(now()->startOfDay());
    }

    /** Passo da recorrencia em [intervalo, unidade]. Null quando nao recorre. */
    public function passoRecorrencia(): ?array
    {
        return match ($this->recorrencia) {
            'mensal' => [1, 'mes'],
            'trimestral' => [3, 'mes'],
            'semestral' => [6, 'mes'],
            'anual' => [1, 'ano'],
            'personalizada' => $this->recorrencia_intervalo && $this->recorrencia_unidade
                ? [(int) $this->recorrencia_intervalo, $this->recorrencia_unidade]
                : null,
            default => null,
        };
    }
}
