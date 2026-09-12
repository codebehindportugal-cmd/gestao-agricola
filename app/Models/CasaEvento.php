<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasaEvento extends Model
{
    protected $table = 'casa_eventos';

    protected $fillable = [
        'entity_id',
        'nome',
        'zona',
        'tipo',
        'estado',
        'ocorreu_em',
    ];

    protected $casts = [
        'ocorreu_em' => 'datetime',
    ];

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(CasaDispositivo::class, 'entity_id', 'entity_id');
    }

    public function scopeRecentes(Builder $query, int $horas = 24): Builder
    {
        return $query->where('ocorreu_em', '>=', now()->subHours($horas));
    }

    public function scopeDoTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }
}
