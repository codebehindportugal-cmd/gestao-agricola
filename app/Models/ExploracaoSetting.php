<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExploracaoSetting extends Model
{
    protected $fillable = [
        'produtor_nome',
        'concelho',
        'freguesia',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'produtor_nome' => null,
            'concelho' => null,
            'freguesia' => null,
        ]);
    }
}
