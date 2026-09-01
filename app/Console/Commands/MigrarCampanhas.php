<?php

namespace App\Console\Commands;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Compromisso;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Operacao;
use App\Models\Receita;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passa as campanhas por parcela a campanhas gerais, uma por especie e ano.
 *
 * Nao corre sozinho: e um comando, e nao uma migracao, precisamente para nao
 * disparar sem querer num deploy. Sem --confirmar so mostra o plano.
 */
class MigrarCampanhas extends Command
{
    protected $signature = 'agri:migrar-campanhas
        {--ano= : Ano da apanha (por omissão o ano corrente)}
        {--inicio= : Início da época, AAAA-MM-DD (por omissão 1 de Outubro do ano anterior)}
        {--fim= : Fim da época, AAAA-MM-DD (por omissão 30 de Setembro do ano da apanha)}
        {--confirmar : Aplica as alterações; sem esta opção apenas mostra o plano}';

    protected $description = 'Agrupa as campanhas por espécie e ano numa campanha geral que cobre todas as parcelas.';

    public function handle(): int
    {
        $ano = (int) ($this->option('ano') ?: now()->year);
        $aplicar = (bool) $this->option('confirmar');

        // A epoca atravessa dois anos civis: poda-se no ano anterior e apanha-se
        // no ano da campanha. E como a aplicacao ja rotula as campanhas (2025/2026).
        $inicio = $this->option('inicio') ?: CarbonImmutable::create($ano - 1, 10, 1)->toDateString();
        $fim = $this->option('fim') ?: CarbonImmutable::create($ano, 9, 30)->toDateString();

        $culturas = Cultura::query()->with('parcela')->get()
            ->filter(fn (Cultura $c) => filled($c->tipo) && $c->parcela !== null);

        if ($culturas->isEmpty()) {
            $this->warn('Não há culturas com parcela associada. Nada a fazer.');

            return self::SUCCESS;
        }

        $porEspecie = $culturas->groupBy(fn (Cultura $c) => $this->normalizarEspecie($c->tipo));

        $this->info($aplicar ? "A migrar campanhas de {$ano}..." : "PLANO para {$ano} (nada será alterado sem --confirmar)");
        $this->line("Época: {$inicio} a {$fim}");
        $this->newLine();

        $totais = ['gerais' => 0, 'parcelas' => 0, 'antigas' => 0, 'registos' => 0];

        foreach ($porEspecie as $especie => $culturasDaEspecie) {
            $nome = $this->plural($especie).' '.$ano;
            $parcelaIds = $culturasDaEspecie->pluck('parcela.id')->unique()->values();

            $antigas = Campanha::query()
                ->whereNull('nome')
                ->where('ano', $ano)
                ->whereIn('cultura_id', $culturasDaEspecie->pluck('id'))
                ->get();

            $registos = $this->contarRegistos($antigas->pluck('id')->all());

            $this->line("<fg=cyan>{$nome}</>");
            $this->line("  parcelas cobertas: {$parcelaIds->count()} — ".
                $culturasDaEspecie->pluck('parcela.nome')->implode(', '));
            $this->line("  campanhas antigas a absorver: {$antigas->count()}".
                ($antigas->isEmpty() ? '' : ' — '.$antigas->map->nome_completo->implode(', ')));
            $this->line("  registos a repontar: {$registos}");
            $this->newLine();

            $totais['gerais']++;
            $totais['parcelas'] += $parcelaIds->count();
            $totais['antigas'] += $antigas->count();
            $totais['registos'] += $registos;

            if (! $aplicar) {
                continue;
            }

            DB::transaction(function () use ($nome, $ano, $inicio, $fim, $parcelaIds, $antigas, $culturasDaEspecie): void {
                $geral = Campanha::query()->firstOrCreate(
                    ['nome' => $nome, 'ano' => $ano],
                    [
                        'cultura_id' => null,
                        'data_inicio' => $inicio,
                        'data_fim' => $fim,
                        'status' => 'em_curso',
                        'observacoes' => 'Campanha geral de '.$this->plural($this->normalizarEspecie($culturasDaEspecie->first()->tipo)).'.',
                    ]
                );

                $geral->parcelas()->syncWithoutDetaching($parcelaIds->all());

                foreach ($antigas as $antiga) {
                    $this->repontar($antiga->id, $geral->id);
                    $antiga->delete();
                }
            });
        }

        $this->info(sprintf(
            '%s %d campanha(s) geral(is), %d parcela(s) ligada(s), %d campanha(s) antiga(s) absorvida(s), %d registo(s) repontado(s).',
            $aplicar ? 'Aplicado:' : 'Seria aplicado:',
            $totais['gerais'],
            $totais['parcelas'],
            $totais['antigas'],
            $totais['registos']
        ));

        if (! $aplicar) {
            $this->newLine();
            $this->comment('Volte a correr com --confirmar para aplicar.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, class-string> */
    private function modelos(): array
    {
        return [Operacao::class, Custo::class, Colheita::class, Receita::class, Despesa::class, Compromisso::class];
    }

    private function contarRegistos(array $antigasIds): int
    {
        if ($antigasIds === []) {
            return 0;
        }

        $total = 0;

        foreach ($this->modelos() as $modelo) {
            $instancia = new $modelo;

            if (! Schema::hasColumn($instancia->getTable(), 'campanha_id')) {
                continue;
            }

            $total += $modelo::query()->whereIn('campanha_id', $antigasIds)->count();
        }

        return $total;
    }

    private function repontar(int $de, int $para): void
    {
        foreach ($this->modelos() as $modelo) {
            $instancia = new $modelo;

            if (! Schema::hasColumn($instancia->getTable(), 'campanha_id')) {
                continue;
            }

            $modelo::query()->where('campanha_id', $de)->update(['campanha_id' => $para]);
        }
    }

    private function normalizarEspecie(string $tipo): string
    {
        return ucfirst(mb_strtolower(trim($tipo)));
    }

    private function plural(string $especie): string
    {
        return str_ends_with(mb_strtolower($especie), 's') ? $especie : $especie.'s';
    }
}
