<?php

namespace App\Console\Commands;

use App\Models\Cultura;
use App\Models\Parcela;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Preenche a especie e a variedade das culturas.
 *
 * Em producao ficou tudo com tipo 'pomar' e variedade vazia, o que torna
 * impossivel agrupar campanhas por especie.
 *
 * A chave e "terreno / parcela" e nao o nome da cultura: os nomes das culturas
 * divergiram entre o ambiente local e producao, os das parcelas nao. E ha duas
 * parcelas chamadas "Casa", pelo que o terreno e necessario para desambiguar.
 *
 * Sem --confirmar apenas mostra o plano.
 */
class ClassificarCulturas extends Command
{
    protected $signature = 'agri:classificar-culturas
        {--confirmar : Aplica as alterações; sem esta opção apenas mostra o plano}
        {--perenes : Marca também os pomares como ciclo_produtivo=perene}';

    protected $description = 'Preenche espécie e variedade das culturas, identificadas por terreno/parcela.';

    /** 'terreno|parcela' => [especie, variedade] */
    private const POR_PARCELA = [
        // Pereiras Rocha
        'Troncos - Pereiras|Troncos Pereiras' => ['Pereira', 'Rocha'],
        'Toino Coito - Cumeira|Antonio Coito' => ['Pereira', 'Rocha'],
        'Buga|Buga Pereiras' => ['Pereira', 'Rocha'],
        'Capoeiro - 2|Caopeiro' => ['Pereira', 'Rocha'],
        'Capoeiro 1|Capoeiro 1' => ['Pereira', 'Rocha'],
        'Pereiras - Casa|Casa' => ['Pereira', 'Rocha'],
        'Cumeira 1|Cumeira' => ['Pereira', 'Rocha'],
        'Cumeira 2|Cumeira 2' => ['Pereira', 'Rocha'],
        'Horta|Horta' => ['Pereira', 'Rocha'],
        'Panasqueira|Panasqueira' => ['Pereira', 'Rocha'],
        'Torre - Pereiras|Torre Pereiras' => ['Pereira', 'Rocha'],
        'Vale da Vaca - Pereiras|Vale da Vaca' => ['Pereira', 'Rocha'],

        // Macieiras
        'Peso - Goldes|Goldes' => ['Macieira', 'Golden'],
        'Torre - Maceiras|Torre' => ['Macieira', 'Jonagold Red'],
        'Troncos - Maceiras|Troncos' => ['Macieira', 'Royal Gala'],
        'Vale Moinho|Vale Moinhoi' => ['Macieira', 'Royal Gala'],
    ];

    /** 'terreno|parcela' => culturas a criar: [nome, especie, variedade] */
    private const A_CRIAR = [
        'Choupinho|Choupinho' => [
            ['Choupinho Fuji', 'Macieira', 'Fuji'],
            ['Choupinho Royal Gala', 'Macieira', 'Royal Gala'],
        ],
        'Terra dos Cachopos|Peso Fujis' => [
            ['Peso Fujis', 'Macieira', 'Fuji'],
        ],
    ];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('confirmar');
        $perenes = (bool) $this->option('perenes');

        $this->info($aplicar ? 'A classificar culturas...' : 'PLANO (nada será alterado sem --confirmar)');
        $this->newLine();

        $parcelas = Parcela::query()->with('terreno')->get()
            ->keyBy(fn (Parcela $p) => ($p->terreno?->nome ?? '?').'|'.$p->nome);

        $classificadas = 0;
        $criadas = 0;
        $semParcela = [];
        $intactas = [];

        $this->line('<fg=cyan>Culturas a classificar</>');

        foreach (self::POR_PARCELA as $chave => [$especie, $variedade]) {
            $parcela = $parcelas->get($chave);

            if (! $parcela) {
                $semParcela[] = $chave;

                continue;
            }

            $culturas = Cultura::query()->where('parcela_id', $parcela->id)->get();

            if ($culturas->isEmpty()) {
                $semParcela[] = $chave.' (parcela existe, sem cultura)';

                continue;
            }

            foreach ($culturas as $cultura) {
                $this->line(sprintf(
                    '  %-26s %-22s %s → %s / %s',
                    $chave,
                    $cultura->nome,
                    $cultura->tipo,
                    $especie,
                    $variedade
                ));
                $classificadas++;

                if (! $aplicar) {
                    continue;
                }

                $dados = ['tipo' => $especie, 'variedade' => $variedade];

                if ($perenes) {
                    $dados['ciclo_produtivo'] = 'perene';
                }

                $cultura->update($dados);
            }
        }

        $this->newLine();
        $this->line('<fg=cyan>Culturas a criar</>');

        foreach (self::A_CRIAR as $chave => $novas) {
            $parcela = $parcelas->get($chave);

            if (! $parcela) {
                $semParcela[] = $chave.' (a criar)';

                continue;
            }

            foreach ($novas as [$nome, $especie, $variedade]) {
                if (Cultura::query()->where('parcela_id', $parcela->id)->where('nome', $nome)->exists()) {
                    $this->line("  {$nome} — já existe, ignorada.");

                    continue;
                }

                $this->line(sprintf('  %-26s %-22s → %s / %s', $chave, $nome, $especie, $variedade));
                $criadas++;

                if (! $aplicar) {
                    continue;
                }

                DB::transaction(fn () => Cultura::query()->create([
                    'parcela_id' => $parcela->id,
                    'nome' => $nome,
                    'tipo' => $especie,
                    'variedade' => $variedade,
                    // Data desconhecida: fica a de hoje e corrige-se na ficha.
                    'data_plantacao' => now()->toDateString(),
                    'estado' => 'em_crescimento',
                    'ciclo_produtivo' => $perenes ? 'perene' : 'anual',
                    'observacoes' => 'Criada por agri:classificar-culturas; confirmar data de plantação.',
                ]));
            }
        }

        // Tudo o que ficou de fora tem de aparecer: e o que se perde de vista.
        $chavesTratadas = array_merge(array_keys(self::POR_PARCELA), array_keys(self::A_CRIAR));

        foreach (Cultura::query()->with('parcela.terreno')->get() as $cultura) {
            $chave = ($cultura->parcela?->terreno?->nome ?? '?').'|'.($cultura->parcela?->nome ?? '?');

            if (! in_array($chave, $chavesTratadas, true)) {
                $intactas[] = sprintf('%s (parcela %s, tipo %s)', $cultura->nome, $chave, $cultura->tipo);
            }
        }

        if ($semParcela !== []) {
            $this->newLine();
            $this->line('<fg=red>NÃO ENCONTRADAS — verificar antes de aplicar</>');

            foreach ($semParcela as $chave) {
                $this->line('  '.$chave);
            }
        }

        if ($intactas !== []) {
            $this->newLine();
            $this->line('<fg=yellow>Ficam como estão (não constam da tabela)</>');

            foreach ($intactas as $nome) {
                $this->line('  '.$nome);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d cultura(s) classificada(s), %d criada(s), %d chave(s) não encontrada(s).%s',
            $aplicar ? 'Aplicado:' : 'Seria aplicado:',
            $classificadas,
            $criadas,
            count($semParcela),
            $perenes ? ' Pomares marcados como perenes.' : ' (use --perenes para corrigir o ciclo produtivo)'
        ));

        if (! $aplicar) {
            $this->newLine();
            $this->comment('Volte a correr com --confirmar para aplicar.');
        }

        return self::SUCCESS;
    }
}
