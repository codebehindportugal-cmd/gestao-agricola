<?php

namespace App\Console\Commands;

use App\Services\GeradorCompromissos;
use Illuminate\Console\Command;

class GerarCompromissos extends Command
{
    protected $signature = 'agri:gerar-compromissos {--meses= : Horizonte em meses (por omissão 18)}';

    protected $description = 'Materializa as ocorrências futuras das séries recorrentes do calendário.';

    public function handle(GeradorCompromissos $gerador): int
    {
        $meses = $this->option('meses') !== null ? (int) $this->option('meses') : null;

        $resultado = $gerador->gerarTodas($meses);

        $this->info(sprintf(
            '%d série(s) processada(s), %d ocorrência(s) criada(s).',
            $resultado['series'],
            $resultado['criados']
        ));

        return self::SUCCESS;
    }
}
