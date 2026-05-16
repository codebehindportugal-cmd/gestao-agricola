<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Funcionario extends Model
{
    use SoftDeletes;

    protected $table = 'funcionarios';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'cargo',
        'aplicador_numero_autorizacao',
        'data_admissao',
        'data_saida',
        'tipo_contrato',
        'valor_hora',
        'status',
        'observacoes',
        'location_token',
        'last_latitude',
        'last_longitude',
        'last_accuracy',
        'last_speed',
        'last_heading',
        'location_shared_at',
        'location_token_refreshed_at',
    ];

    protected $casts = [
        'data_admissao' => 'date',
        'data_saida' => 'date',
        'valor_hora' => 'decimal:2',
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
        'last_speed' => 'decimal:2',
        'last_heading' => 'decimal:2',
        'location_shared_at' => 'datetime',
        'location_token_refreshed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Funcionario $funcionario) {
            if (! $funcionario->location_token) {
                $funcionario->location_token = static::generateLocationToken();
                $funcionario->location_token_refreshed_at = now();
            }
        });
    }

    public static function generateLocationToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('location_token', $token)->exists());

        return $token;
    }

    // Relacionamentos
    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class);
    }

    public function equipas(): BelongsToMany
    {
        return $this->belongsToMany(Equipa::class, 'equipa_funcionario')
            ->withTimestamps();
    }

    public function equipasLideradas(): HasMany
    {
        return $this->hasMany(Equipa::class, 'lider_id');
    }

    public function custos(): HasMany
    {
        return $this->hasMany(Custo::class);
    }

    public function hasLocation(): bool
    {
        return $this->last_latitude !== null && $this->last_longitude !== null;
    }
}
