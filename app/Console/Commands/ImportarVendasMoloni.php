<?php

namespace App\Console\Commands;

use App\Models\Campanha;
use App\Services\Moloni\ImportadorVendasMoloni;
use App\Services\Moloni\MoloniException;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportarVendasMoloni extends Command
{
    protected $signature = 'agri:importar-moloni
        {--campanha= : id ou nome da campanha (por omissao a mais recente)}
        {--de= : data inicial (AAAA-MM-DD), por omissao o inicio da campanha}
        {--ate= : data final, por omissao hoje ou o fim da campanha}';

    protected $description = 'Importa as vendas (FT, FR, FS) do Moloni para as receitas da campanha';

    public function handle(ImportadorVendasMoloni $importador): int
    {
        $referencia = $this->option('campanha');
        $campanha = $referencia
            ? Campanha::query()->whereKey($referencia)->orWhere('nome', $referencia)->first()
            : Campanha::query()->orderByDesc('data_inicio')->first();

        if ($campanha === null) {
            $this->error('Campanha nao encontrada.');

            return self::FAILURE;
        }

        try {
            $resumo = $importador->importar(
                $campanha,
                $this->option('de') ? CarbonImmutable::parse($this->option('de')) : null,
                $this->option('ate') ? CarbonImmutable::parse($this->option('ate')) : null,
            );
        } catch (MoloniException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Campanha {$campanha->nome_completo}, {$resumo['de']} a {$resumo['ate']}");
        $this->table(['Documentos', 'Vendas novas', 'Ja existiam', 'Sem especie', 'Valor', 'Kg'], [[
            $resumo['documentos'], $resumo['criadas'], $resumo['existentes'], $resumo['sem_especie'],
            number_format($resumo['valor'], 2, ',', ' '), number_format($resumo['kg'], 0, ',', ' '),
        ]]);

        foreach ($resumo['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        return self::SUCCESS;
    }
}
