<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operacao extends Model
{
    use SoftDeletes;

    protected $table = 'operacoes';

    protected $fillable = [
        'parcela_id',
        'cultura_id',
        'campanha_id',
        'tipo',
        'data_hora_inicio',
        'data_hora_fim',
        'maquina_id',
        'alfaia_id',
        'operador_id',
        'funcionario_id',
        'equipa_id',
        'produtor_nome',
        'aplicador_nome',
        'aplicador_numero_autorizacao',
        'exploracao_concelho',
        'exploracao_freguesia',
        'duracao_horas',
        'distancia_km',
        'combustivel_gasto_l',
        'custo_estimado',
        'custo_real',
        'estado',
        'observacoes',
        'image_path',
    ];

    protected $casts = [
        'data_hora_inicio' => 'datetime',
        'data_hora_fim' => 'datetime',
        'duracao_horas' => 'decimal:2',
        'distancia_km' => 'decimal:2',
        'combustivel_gasto_l' => 'decimal:2',
        'custo_estimado' => 'decimal:2',
        'custo_real' => 'decimal:2',
    ];

    // Relacionamentos
    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function cultura(): BelongsTo
    {
        return $this->belongsTo(Cultura::class);
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    public function alfaia(): BelongsTo
    {
        return $this->belongsTo(Alfaia::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operador_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function equipa(): BelongsTo
    {
        return $this->belongsTo(Equipa::class);
    }

    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'operacao_produtos')
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

    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class);
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }
}
