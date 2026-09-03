<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompromissoAviso extends Model
{
    /** Aviso enviado depois de o prazo ja ter passado. */
    public const ATRASO = -1;

    protected $table = 'compromisso_avisos';

    protected $fillable = [
        'compromisso_id',
        'dias_antes',
        'enviado_em',
    ];

    protected $casts = [
        'dias_antes' => 'integer',
        'enviado_em' => 'datetime',
    ];

    public function compromisso(): BelongsTo
    {
        return $this->belongsTo(Compromisso::class);
    }
}
