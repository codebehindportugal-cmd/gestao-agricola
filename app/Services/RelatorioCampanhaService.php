<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Produto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Relatorio da campanha inteira: custos, vendas e margem de 1 de outubro a
 * 30 de setembro, e nao so mes a mes como no ecra das despesas.
 *
 * Os custos saem das mesmas quatro parcelas que a Campanha usa para o
 * custo_total_calculado (operacoes, produtos aplicados, custos directos e a
 * parte dos partilhados), so que arrumados por mes e por rubrica. A soma das
 * colunas bate com o total da campanha — e o teste que o garante.
 *
 * - Operacao: vale o maior entre o custo_real e os Custos ligados (a regra da
 *   Campanha). Os ligados entram na sua rubrica (mao de obra, maquinaria); se
 *   o custo_real for maior, a diferenca entra como "outro".
 * - Produtos aplicados entram no mes da operacao.
 * - Custos directos (faturas, IMI, seguros) entram no mes do custo.
 * - Partilhados (luz das regas, frio) entram no mes do custo, pela quota que
 *   o RateioCustosService da a esta campanha.
 */
class RelatorioCampanhaService
{
    public const RUBRICAS = [
        'mao_obra' => 'Mão de obra',
        'maquinaria' => 'Máquinas',
        'material' => 'Material e faturas',
        'produtos' => 'Produtos aplicados',
        'energia' => 'Energia',
        'manutencao' => 'Manutenção',
        'partilhados' => 'Partilhados (rateio)',
        'outro' => 'Outros',
    ];

    public function __construct(private readonly ResumoPorEspecieService $resumoEspecies)
    {
    }

    public function paraCampanha(Campanha $campanha): array
    {
        $campanha->loadMissing([
            'operacoes.produtos',
            'custos',
            'colheitas',
            'receitas',
        ]);

        $meses = $this->mesesDaCampanha($campanha);
        $porMes = [];
        foreach ($meses as $chave => $rotulo) {
            $porMes[$chave] = $this->linhaVazia($chave, $rotulo);
        }
        $semData = $this->linhaVazia('sem_data', 'Sem data');

        $idsOperacoes = $campanha->operacoes->pluck('id')->all();
        $ligados = $campanha->custos
            ->filter(fn (Custo $custo) => $custo->operacao_id !== null && in_array($custo->operacao_id, $idsOperacoes))
            ->groupBy('operacao_id');

        // --- Operacoes ---------------------------------------------------------
        foreach ($campanha->operacoes as $operacao) {
            $chave = $this->chaveMes($operacao->data_hora_inicio);
            /** @var Collection<int, Custo> $custosDaOperacao */
            $custosDaOperacao = $ligados->get($operacao->id, collect());
            $somaLigados = (float) $custosDaOperacao->sum('valor');
            $custoReal = (float) ($operacao->custo_real ?? 0);

            foreach ($custosDaOperacao as $custo) {
                $this->somar($porMes, $semData, $chave, $this->rubrica($custo->tipo), (float) $custo->valor);
            }

            if ($custoReal > $somaLigados) {
                $this->somar($porMes, $semData, $chave, 'outro', round($custoReal - $somaLigados, 2));
            }

            $produtos = $this->custoDosProdutos($operacao);
            if ($produtos > 0) {
                $this->somar($porMes, $semData, $chave, 'produtos', $produtos);
            }
        }

        // --- Custos directos ---------------------------------------------------
        foreach ($campanha->custosAvulsos() as $custo) {
            $this->somar($porMes, $semData, $this->chaveMes($custo->data_custo), $this->rubrica($custo->tipo), (float) $custo->valor);
        }

        // --- Partilhados -------------------------------------------------------
        foreach ($campanha->detalheRateio() as $linha) {
            $this->somar($porMes, $semData, $this->chaveMes($linha['data']), 'partilhados', (float) $linha['valor']);
        }

        // --- Colheitas e vendas ------------------------------------------------
        foreach ($campanha->colheitas as $colheita) {
            $chave = $this->chaveMes($colheita->data_colheita);
            $alvo = &$this->alvo($porMes, $semData, $chave);
            $alvo['kg_colhidos'] += (float) $colheita->quantidade_total;
            unset($alvo);
        }

        foreach ($campanha->receitas as $receita) {
            $chave = $this->chaveMes($receita->data);
            $alvo = &$this->alvo($porMes, $semData, $chave);
            $alvo['vendas'] += (float) $receita->valor;
            $alvo['kg_vendidos'] += (float) ($receita->quantidade ?? 0);
            unset($alvo);
        }

        $linhas = array_values($porMes);
        if ($this->temValores($semData)) {
            $linhas[] = $semData;
        }

        $linhas = array_map(fn (array $linha) => $this->fechar($linha), $linhas);

        // Acumulado ao longo da campanha, para o grafico e para a tabela.
        $acumCustos = 0.0;
        $acumVendas = 0.0;
        foreach ($linhas as &$linha) {
            $acumCustos += $linha['custos'];
            $acumVendas += $linha['vendas'];
            $linha['custos_acumulado'] = round($acumCustos, 2);
            $linha['vendas_acumulado'] = round($acumVendas, 2);
        }
        unset($linha);

        $porRubrica = [];
        foreach (array_keys(self::RUBRICAS) as $rubrica) {
            $porRubrica[$rubrica] = round(array_sum(array_map(fn ($l) => $l['rubricas'][$rubrica], $linhas)), 2);
        }

        $custoTotal = round(array_sum($porRubrica), 2);
        $vendas = round(array_sum(array_column($linhas, 'vendas')), 2);
        $kgColhidos = round(array_sum(array_column($linhas, 'kg_colhidos')), 2);
        $kgVendidos = round(array_sum(array_column($linhas, 'kg_vendidos')), 2);

        return [
            'campanha' => [
                'id' => $campanha->id,
                'nome' => $campanha->nome_completo,
                'inicio' => $campanha->data_inicio?->toDateString(),
                'fim' => $campanha->data_fim?->toDateString(),
                'status' => $campanha->status,
                'area_ha' => $campanha->area_total_ha,
            ],
            'total' => [
                'custos' => $custoTotal,
                'vendas' => $vendas,
                'margem' => round($vendas - $custoTotal, 2),
                'kg_colhidos' => $kgColhidos,
                'kg_vendidos' => $kgVendidos,
                'custo_por_kg' => $kgColhidos > 0 ? round($custoTotal / $kgColhidos, 4) : 0,
                'preco_medio' => $kgVendidos > 0 ? round($this->vendasComQuantidade($campanha) / $kgVendidos, 4) : 0,
            ],
            'rubricas' => self::RUBRICAS,
            'por_rubrica' => $porRubrica,
            'por_mes' => $linhas,
            'por_especie' => $this->resumoEspecies->paraCampanha($campanha),
        ];
    }

    /** @return array<string, string> "2025-10" => "out 2025", ... */
    private function mesesDaCampanha(Campanha $campanha): array
    {
        $inicio = $campanha->data_inicio ? CarbonImmutable::parse($campanha->data_inicio) : null;
        $fim = $campanha->data_fim ? CarbonImmutable::parse($campanha->data_fim) : null;

        if ($inicio === null) {
            return [];
        }

        $fim ??= CarbonImmutable::now();
        $meses = [];

        for ($mes = $inicio->startOfMonth(); $mes->lessThanOrEqualTo($fim) && count($meses) < 36; $mes = $mes->addMonth()) {
            $meses[$mes->format('Y-m')] = $this->rotuloMes($mes);
        }

        return $meses;
    }

    private function rotuloMes(CarbonImmutable $mes): string
    {
        $nomes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        return $nomes[$mes->month - 1].' '.$mes->year;
    }

    private function chaveMes(mixed $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        return CarbonImmutable::parse($data)->format('Y-m');
    }

    private function linhaVazia(string $chave, string $rotulo): array
    {
        return [
            'mes' => $chave,
            'rotulo' => $rotulo,
            'rubricas' => array_fill_keys(array_keys(self::RUBRICAS), 0.0),
            'custos' => 0.0,
            'vendas' => 0.0,
            'kg_colhidos' => 0.0,
            'kg_vendidos' => 0.0,
        ];
    }

    /**
     * A linha do mes, ou uma linha nova quando o registo cai fora do periodo
     * da campanha (acontece com custos lancados com a data errada).
     */
    private function &alvo(array &$porMes, array &$semData, ?string $chave): array
    {
        if ($chave === null) {
            return $semData;
        }

        if (! isset($porMes[$chave])) {
            $porMes[$chave] = $this->linhaVazia($chave, $this->rotuloMes(CarbonImmutable::parse($chave.'-01')));
            ksort($porMes);
        }

        return $porMes[$chave];
    }

    private function somar(array &$porMes, array &$semData, ?string $chave, string $rubrica, float $valor): void
    {
        if ($valor == 0.0) {
            return;
        }

        $alvo = &$this->alvo($porMes, $semData, $chave);
        $alvo['rubricas'][$rubrica] += $valor;
        unset($alvo);
    }

    private function fechar(array $linha): array
    {
        $linha['rubricas'] = array_map(fn ($v) => round($v, 2), $linha['rubricas']);
        $linha['custos'] = round(array_sum($linha['rubricas']), 2);
        $linha['vendas'] = round($linha['vendas'], 2);
        $linha['kg_colhidos'] = round($linha['kg_colhidos'], 2);
        $linha['kg_vendidos'] = round($linha['kg_vendidos'], 2);
        $linha['margem'] = round($linha['vendas'] - $linha['custos'], 2);

        return $linha;
    }

    private function temValores(array $linha): bool
    {
        return array_sum($linha['rubricas']) != 0.0
            || $linha['vendas'] != 0.0
            || $linha['kg_colhidos'] != 0.0;
    }

    private function rubrica(?string $tipo): string
    {
        return match ($tipo) {
            'mao_obra' => 'mao_obra',
            'maquina', 'maquinaria', 'combustivel' => 'maquinaria',
            'material', 'fertilizantes', 'fitofarmaceuticos', 'sementes' => 'material',
            'energia' => 'energia',
            'manutencao', 'pecas' => 'manutencao',
            default => 'outro',
        };
    }

    private function custoDosProdutos(Operacao $operacao): float
    {
        return round((float) $operacao->produtos->sum(function (Produto $produto) {
            if ($produto->pivot?->custo_total !== null) {
                return (float) $produto->pivot->custo_total;
            }

            if ($produto->pivot?->custo_unitario === null) {
                return 0;
            }

            return round((float) ($produto->pivot->quantidade ?? 0) * (float) $produto->pivot->custo_unitario, 2);
        }), 2);
    }

    private function vendasComQuantidade(Campanha $campanha): float
    {
        return (float) $campanha->receitas
            ->filter(fn ($receita) => (float) ($receita->quantidade ?? 0) > 0)
            ->sum('valor');
    }
}
