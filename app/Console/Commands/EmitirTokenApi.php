<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EmitirTokenApi extends Command
{
    protected $signature = 'agri:emitir-token {email} {--nome=} {--abilities=*}';

    protected $description = 'Emite um token pessoal para a API de ingestao agricola.';

    private const ABILITIES_PERMITIDAS = [
        'custos:write',
        'aplicacoes:write',
        'colheitas:write',
        'receitas:write',
    ];

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Utilizador nao encontrado.');

            return self::FAILURE;
        }

        if (! $user->hasRole(['admin', 'gestor_agricola', 'operador'])) {
            $this->error('Utilizador sem role autorizado para escrever na API.');

            return self::FAILURE;
        }

        $abilities = $this->option('abilities') ?: self::ABILITIES_PERMITIDAS;
        $invalidas = array_values(array_diff($abilities, self::ABILITIES_PERMITIDAS));

        if ($invalidas !== []) {
            $this->error('Abilities invalidas: '.implode(', ', $invalidas));
            $this->line('Abilities permitidas: '.implode(', ', self::ABILITIES_PERMITIDAS));

            return self::FAILURE;
        }

        $nome = $this->option('nome') ?: 'api-ingestao';

        $this->line($user->createToken($nome, $abilities)->plainTextToken);

        return self::SUCCESS;
    }
}
