<?php

namespace App\Console\Commands;

use App\Models\Despesa;
use App\Models\Operacao;
use App\Services\CompressorImagens;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprime as fotografias que ja' estao no disco.
 *
 * O CompressorImagens trata do que entra a partir de agora; isto e' para o que
 * entrou antes — as faturas fotografadas desde Junho, cada uma com os seus 3 a
 * 5 MB de telemovel.
 *
 * Anda pelos registos e nao pelas pastas de proposito: ao mudar a extensao
 * (.jpg -> .webp) ha' que corrigir o caminho guardado na linha, senao a foto
 * fica no disco e a pagina mostra um 404. Um ficheiro que nao esteja preso a
 * nenhum registo fica onde esta'.
 *
 * Sem --confirmar mostra apenas o que faria, incluindo quanto ia poupar.
 */
class ComprimirFotos extends Command
{
    protected $signature = 'agri:comprimir-fotos
        {--so= : Limita a faturas ou operacoes}
        {--limite=0 : Trata no maximo N fotografias (0 = todas)}
        {--confirmar : Aplica; sem esta opção apenas mostra o que faria}';

    protected $description = 'Reduz as fotografias já guardadas (faturas e operações) sem perder o registo.';

    public function handle(CompressorImagens $compressor): int
    {
        $aplicar = (bool) $this->option('confirmar');
        $so = $this->option('so');
        $limite = (int) $this->option('limite');

        if (! config('imagens.comprimir')) {
            $this->warn('A compressão está desligada (IMAGENS_COMPRIMIR=false). Nada a fazer.');

            return self::SUCCESS;
        }

        $this->info($aplicar ? 'A comprimir as fotografias já guardadas...' : 'PLANO (nada será alterado sem --confirmar)');
        $this->line(sprintf(
            'Lado máximo %d px, qualidade %d, formato %s.',
            config('imagens.lado_maximo'),
            config('imagens.qualidade'),
            config('imagens.formato')
        ));
        $this->newLine();

        $totais = ['tratadas' => 0, 'antes' => 0, 'depois' => 0, 'saltadas' => 0, 'erros' => 0];

        if ($so !== 'operacoes') {
            $this->processar(
                'faturas',
                Despesa::query()->whereNotNull('ficheiro_path'),
                'ficheiro_path',
                $compressor,
                $aplicar,
                $limite,
                $totais
            );
        }

        if ($so !== 'faturas') {
            $this->processar(
                'operações',
                Operacao::query()->whereNotNull('image_path'),
                'image_path',
                $compressor,
                $aplicar,
                $limite,
                $totais
            );
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d fotografia(s): %s -> %s (menos %s). %d sem ganho, %d com erro.',
            $aplicar ? 'Comprimidas' : 'Seriam comprimidas',
            $totais['tratadas'],
            $this->emMb($totais['antes']),
            $this->emMb($totais['depois']),
            $this->emMb($totais['antes'] - $totais['depois']),
            $totais['saltadas'],
            $totais['erros']
        ));

        if (! $aplicar) {
            $this->newLine();
            $this->comment('Volte a correr com --confirmar para aplicar.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, int>  $totais
     */
    private function processar(
        string $rotulo,
        Builder $query,
        string $coluna,
        CompressorImagens $compressor,
        bool $aplicar,
        int $limite,
        array &$totais
    ): void {
        $this->line("<fg=cyan>{$rotulo}</>");

        $tratadasAqui = 0;

        $query->orderBy('id')->chunkById(100, function ($registos) use (
            $coluna, $compressor, $aplicar, $limite, &$totais, &$tratadasAqui
        ) {
            foreach ($registos as $registo) {
                if ($limite > 0 && $totais['tratadas'] >= $limite) {
                    return false;
                }

                $caminho = (string) $registo->{$coluna};

                if (! Storage::disk('public')->exists($caminho)) {
                    $totais['saltadas']++;

                    continue;
                }

                $antes = (int) Storage::disk('public')->size($caminho);

                if (! $aplicar) {
                    // Sem mexer no disco: so' se estima pelo tamanho, que e' o
                    // que interessa para decidir se vale a pena correr.
                    if ($antes < config('imagens.minimo_bytes')) {
                        $totais['saltadas']++;

                        continue;
                    }

                    $totais['tratadas']++;
                    $totais['antes'] += $antes;
                    // ~15% do original e' o que se tem visto numa foto de
                    // telemovel reduzida a 2000 px em webp.
                    $totais['depois'] += (int) round($antes * 0.15);
                    $tratadasAqui++;

                    continue;
                }

                try {
                    $resultado = $compressor->comprimirNoDisco($caminho);
                } catch (Throwable $excepcao) {
                    $this->warn("  #{$registo->id}: {$excepcao->getMessage()}");
                    $totais['erros']++;

                    continue;
                }

                if ($resultado === null) {
                    $totais['saltadas']++;

                    continue;
                }

                if ($resultado['caminho'] !== $caminho) {
                    $registo->forceFill([$coluna => $resultado['caminho']])->save();
                }

                $totais['tratadas']++;
                $totais['antes'] += $resultado['antes'];
                $totais['depois'] += $resultado['depois'];
                $tratadasAqui++;
            }

            return true;
        });

        $this->line('  '.($tratadasAqui === 0 ? 'nada a fazer' : $tratadasAqui.' fotografia(s)'));
    }

    private function emMb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1, ',', ' ').' MB';
    }
}
