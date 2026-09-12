<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasaDispositivo extends Model
{
    /** Tipos que geram histórico em casa_eventos. Os restantes são só estado. */
    public const TIPOS_COM_HISTORICO = [
        'movimento',
        'porta',
        'janela',
        'agua',
        'fumo',
    ];

    /** device_class do Home Assistant -> tipo nosso. */
    public const DEVICE_CLASS_PARA_TIPO = [
        'motion' => 'movimento',
        'occupancy' => 'movimento',
        'presence' => 'movimento',
        'door' => 'porta',
        'garage_door' => 'porta',
        'opening' => 'porta',
        'window' => 'janela',
        'moisture' => 'agua',
        'smoke' => 'fumo',
        'gas' => 'fumo',
        'temperature' => 'temperatura',
        'humidity' => 'humidade',
    ];

    protected $table = 'casa_dispositivos';

    protected $attributes = [
        'tipo' => 'outro',
        'visivel' => true,
        'ordem' => 0,
    ];

    protected $fillable = [
        'entity_id',
        'nome',
        'zona',
        'tipo',
        'estado',
        'atributos',
        'visto_em',
        'visivel',
        'ordem',
    ];

    protected $casts = [
        'atributos' => 'array',
        'visto_em' => 'datetime',
        'visivel' => 'boolean',
    ];

    protected $appends = ['activo'];

    public function eventos(): HasMany
    {
        return $this->hasMany(CasaEvento::class, 'entity_id', 'entity_id');
    }

    public function scopeVisiveis(Builder $query): Builder
    {
        return $query->where('visivel', true);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', 'on');
    }

    /** Um sensor accionado neste momento. */
    public function getActivoAttribute(): bool
    {
        return $this->estado === 'on';
    }

    public function geraHistorico(): bool
    {
        return in_array($this->tipo, self::TIPOS_COM_HISTORICO, true);
    }

    public static function tipoDeDeviceClass(?string $deviceClass): string
    {
        if ($deviceClass === null || $deviceClass === '') {
            return 'outro';
        }

        return self::DEVICE_CLASS_PARA_TIPO[$deviceClass] ?? 'outro';
    }
}
