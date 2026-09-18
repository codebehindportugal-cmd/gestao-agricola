<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manutencao extends Model
{
    use SoftDeletes;

    protected $table = 'manutencoes';

    protected $fillable = [
        'maquina_id',
        'alfaia_id',
        'data_manutencao',
        'tipo',
        'descricao',
        'custo',
        'duracao_minutos',
        'proxima_manutencao',
        'observacoes',
    ];

    protected $casts = [
        'data_manutencao' => 'date',
        'proxima_manutencao' => 'date',
        'custo' => 'decimal:2',
    ];

    // Relacionamentos
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }

    /**
     * A alfaia revista, quando a manutencao e dela e nao do tractor.
     *
     * Uma manutencao tem maquina, alfaia, ou as duas (a revisao do conjunto):
     * o que nao pode e nao ter nenhuma. Ver StoreManutencaoRequest.
     */
    public function alfaia(): BelongsTo
    {
        return $this->belongsTo(Alfaia::class);
    }

    /** O equipamento a que a manutencao pertence, para listas e titulos. */
    public function getEquipamentoNomeAttribute(): string
    {
        $nomes = array_filter([$this->maquina?->nome, $this->alfaia?->nome]);

        return $nomes === [] ? '—' : implode(' + ', $nomes);
    }
}
