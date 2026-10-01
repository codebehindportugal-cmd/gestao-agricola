<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Produto;
use Illuminate\Support\Collection;

/**
 * A campanha repartida por especie: pereira, macieira, culturas anuais.
 *
 * Com uma campanha unica por epoca (18/09/2026) a margem por especie deixou de
 * sair da lista de campanhas — havia uma campanha por especie e bastava olhar
 * para os cartoes. Sai agora daqui: cada operacao, colheita, custo e venda
 * pertence a uma cultura ou a uma parcela, e a cultura sabe a sua especie
 * (a coluna `tipo`).
 *
 * O que nao pertence a nenhuma cultura nem parcela — a luz das regas, o frio
 * das camaras, uma fatura de adubo para a exploracao toda — e repartido pelos
 * quilos colhidos, a mesma regra que o RateioCustosService usava entre
 * campanhas. Nao havendo quilos em lado nenhum, reparte-se por area; nem isso,
 * em partes iguais.
 *
 * As quatro parcelas de custo sao as mesmas que a Campanha usa (operacoes,
 * produtos, directos, rateados), so que separadas por especie: a soma das
 * linhas bate com o total da campanha.
 */
class ResumoPorEspecieService
{
    /** Registos que nao dizem a que especie pertencem. */
    public const SEM_ESPECIE = 'Sem espécie';

    /**
     * @return array{
     *     campanha: array<string, mixed>,
     *     especies: array<int, array<string, mixed>>,
     *     total: array<string, mixed>
     * }
     */
    public function paraCampanha(Campanha $campanha): array
    {
        $campanha->loadMissing([
            'operacoes',
            'operacoes.produtos',
            'custos',
            'colheitas',
            'receitas',
            'parcelas',
            'cultura.parcela',
        ]);

        [$especiePorCultura, $especiePorParcela] = $this->mapasDeEspecie($campanha);

        $linhas = [];
        $bolsaComum = 0.0;

        // --- Operacoes: o custo proprio e os produtos aplicados ---------------
        $custosLigados = $campanha->custos
            ->filter(fn (Custo $custo) => $custo->operacao_id !== null)
            ->groupBy('operacao_id')
            ->map(fn (Collection $grupo) => (float) $grupo->sum('valor'));

        foreach ($campanha->operacoes as $operacao) {
            $especie = $this->especieDe($operacao->cultura_id, $operacao->parcela_id, $especiePorCultura, $especiePorParcela);
            $linha = &$this->linha($linhas, $especie);

            // O maior dos dois e nunca a soma: um Custo com operacao_id e a
            // mesma despesa que a operacao ja regista em custo_real.
            $linha['custo_operacoes'] += round(max(
                (float) ($operacao->custo_real ?? 0),
                (float) $custosLigados->get($operacao->id, 0)
            ), 2);

            $linha['custo_produtos'] += $this->custoDosProdutos($operacao);

            if ($this->ehTratamento($operacao)) {
                $linha['tratamentos']++;
            }

            unset($linha);
        }

        // --- Custos avulsos: os que nao pertencem a nenhuma operacao ----------
        $idsOperacoes = $campanha->operacoes->pluck('id')->all();

        foreach ($campanha->custos as $custo) {
            if ($custo->operacao_id !== null && in_array($custo->operacao_id, $idsOperacoes, true)) {
                continue;
            }

            $especie = $this->especieDe($custo->cultura_id, $custo->parcela_id, $especiePorCultura, $especiePorParcela, false);

            if ($especie === null) {
                // Gasto da exploracao inteira: fica para o rateio la em baixo.
                $bolsaComum += (float) $custo->valor;

                continue;
            }

            $linha = &$this->linha($linhas, $especie);
            $linha['custo_diretos'] += (float) $custo->valor;
            unset($linha);
        }

        // --- Colheitas --------------------------------------------------------
        foreach ($campanha->colheitas as $colheita) {
            $especie = $this->especieDe($colheita->cultura_id, $colheita->parcela_id, $especiePorCultura, $especiePorParcela);
            $linha = &$this->linha($linhas, $especie);
            $linha['kg'] += (float) $colheita->quantidade_total;
            unset($linha);
        }

        // --- Vendas -----------------------------------------------------------
        foreach ($campanha->receitas as $receita) {
            // Sem cultura nem parcela, a especie gravada na venda (as vendas
            // importadas do Moloni so sabem o artigo: "Pera Rocha").
            $especie = ($receita->cultura_id === null && $receita->parcela_id === null && filled($receita->especie))
                ? $this->especie($receita->especie)
                : $this->especieDe($receita->cultura_id, $receita->parcela_id, $especiePorCultura, $especiePorParcela);
            $linha = &$this->linha($linhas, $especie);
            $linha['vendas'] += (float) $receita->valor;
            $linha['kg_vendidos'] += (float) ($receita->quantidade ?? 0);
            unset($linha);
        }

        // --- Area, para o rateio e para o ecra --------------------------------
        foreach ($this->areaPorEspecie($campanha, $especiePorCultura) as $especie => $area) {
            $linha = &$this->linha($linhas, $especie);
            $linha['area_ha'] += $area;
            unset($linha);
        }

        $this->ratear($linhas, $bolsaComum);

        return [
            'campanha' => [
                'id' => $campanha->id,
                'nome' => $campanha->nome_completo,
                'inicio' => $campanha->data_inicio?->toDateString(),
                'fim' => $campanha->data_fim?->toDateString(),
            ],
            'especies' => $this->ordenar($linhas),
            'total' => $this->totalizar($linhas),
        ];
    }

    /**
     * Cultura -> especie, e parcela -> especie.
     *
     * A parcela so ganha especie quando todas as suas culturas sao da mesma:
     * numa parcela com pereiras e macieiras nao da para adivinhar a quem
     * pertence um custo que so diz a parcela.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function mapasDeEspecie(Campanha $campanha): array
    {
        $parcelaIds = $campanha->parcelasEfetivas()->pluck('id')->all();

        $culturas = Cultura::query()
            ->when($parcelaIds !== [], fn ($query) => $query->whereIn('parcela_id', $parcelaIds))
            ->get(['id', 'nome', 'tipo', 'parcela_id']);

        $porCultura = [];
        $candidatasPorParcela = [];

        foreach ($culturas as $cultura) {
            $especie = $this->especie($cultura->tipo);
            $porCultura[(int) $cultura->id] = $especie;

            if ($cultura->parcela_id !== null) {
                $candidatasPorParcela[(int) $cultura->parcela_id][$especie] = true;
            }
        }

        $porParcela = [];

        foreach ($candidatasPorParcela as $parcelaId => $candidatas) {
            $nomes = array_keys($candidatas);

            if (count($nomes) === 1) {
                $porParcela[$parcelaId] = $nomes[0];
            }
        }

        return [$porCultura, $porParcela];
    }

    /**
     * A especie de um registo: pela cultura, e so depois pela parcela.
     *
     * @param  array<int, string>  $porCultura
     * @param  array<int, string>  $porParcela
     * @param  bool  $comFallback  false devolve null em vez de "Sem especie",
     *                             para o chamador poder mandar o valor para o rateio
     */
    private function especieDe(
        ?int $culturaId,
        ?int $parcelaId,
        array $porCultura,
        array $porParcela,
        bool $comFallback = true
    ): ?string {
        if ($culturaId !== null && isset($porCultura[$culturaId])) {
            return $porCultura[$culturaId];
        }

        if ($parcelaId !== null && isset($porParcela[$parcelaId])) {
            return $porParcela[$parcelaId];
        }

        return $comFallback ? self::SEM_ESPECIE : null;
    }

    /**
     * "pereira", "PEREIRA", " Pereira " -> "Pereira".
     *
     * Sem MB_CASE_TITLE: "culturas_anuais" viraria "Culturas_Anuais". O
     * sublinhado passa a espaco e so a primeira letra sobe, como no
     * agri:migrar-campanhas.
     */
    private function especie(?string $tipo): string
    {
        $limpo = trim(str_replace('_', ' ', (string) $tipo));

        if ($limpo === '') {
            return self::SEM_ESPECIE;
        }

        $minusculas = mb_strtolower($limpo, 'UTF-8');

        return mb_strtoupper(mb_substr($minusculas, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($minusculas, 1, null, 'UTF-8');
    }

    /** @return array<string, mixed> */
    private function &linha(array &$linhas, string $especie): array
    {
        if (! isset($linhas[$especie])) {
            $linhas[$especie] = [
                'especie' => $especie,
                'tratamentos' => 0,
                'area_ha' => 0.0,
                'kg' => 0.0,
                'kg_vendidos' => 0.0,
                'vendas' => 0.0,
                'custo_operacoes' => 0.0,
                'custo_produtos' => 0.0,
                'custo_diretos' => 0.0,
                'custo_rateado' => 0.0,
            ];
        }

        return $linhas[$especie];
    }

    private function custoDosProdutos(Operacao $operacao): float
    {
        return (float) $operacao->produtos->sum(function (Produto $produto) {
            if ($produto->pivot?->custo_total !== null) {
                return (float) $produto->pivot->custo_total;
            }

            if ($produto->pivot?->custo_unitario === null) {
                return 0.0;
            }

            return round((float) ($produto->pivot->quantidade ?? 0) * (float) $produto->pivot->custo_unitario, 2);
        });
    }

    private function ehTratamento(Operacao $operacao): bool
    {
        return $this->normalizar($operacao->tipo) === 'tratamento fitossanitario';
    }

    private function normalizar(?string $valor): string
    {
        $texto = mb_strtolower(trim((string) $valor));

        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);

        return (string) preg_replace('/\s+/u', ' ', $texto);
    }

    /**
     * Hectares por especie, somando as parcelas de cada cultura.
     *
     * @param  array<int, string>  $especiePorCultura
     * @return array<string, float>
     */
    private function areaPorEspecie(Campanha $campanha, array $especiePorCultura): array
    {
        $parcelas = $campanha->parcelasEfetivas()->keyBy('id');

        if ($parcelas->isEmpty()) {
            return [];
        }

        $culturas = Cultura::query()
            ->whereIn('id', array_keys($especiePorCultura))
            ->get(['id', 'parcela_id']);

        // Uma parcela conta uma vez por especie, mesmo com varias culturas da
        // mesma especie lá dentro — senão os hectares vinham a dobrar.
        $parcelasPorEspecie = [];

        foreach ($culturas as $cultura) {
            $especie = $especiePorCultura[(int) $cultura->id] ?? self::SEM_ESPECIE;
            $parcelasPorEspecie[$especie][(int) $cultura->parcela_id] = true;
        }

        $areas = [];

        foreach ($parcelasPorEspecie as $especie => $ids) {
            $areas[$especie] = round((float) collect(array_keys($ids))
                ->map(fn (int $id) => (float) ($parcelas->get($id)?->area_util
                    ?: $parcelas->get($id)?->area_total
                    ?: 0))
                ->sum(), 4);
        }

        return $areas;
    }

    /**
     * Reparte pela especie o que nao pertence a nenhuma.
     *
     * Por quilos colhidos, que e como o custo/kg faz sentido. Sem quilos, por
     * area; sem area, em partes iguais — mais vale imputar por igual do que
     * deixar o gasto de fora da conta.
     *
     * @param  array<string, array<string, mixed>>  $linhas
     */
    private function ratear(array &$linhas, float $bolsaComum): void
    {
        if ($bolsaComum <= 0 || $linhas === []) {
            return;
        }

        $pesos = $this->pesos($linhas);
        $total = array_sum($pesos);

        if ($total <= 0) {
            return;
        }

        $atribuido = 0.0;
        $ultima = array_key_last($pesos);

        foreach ($pesos as $especie => $peso) {
            // A ultima leva o resto, para a soma bater certo ao cêntimo.
            $quota = $especie === $ultima
                ? round($bolsaComum - $atribuido, 2)
                : round($bolsaComum * $peso / $total, 2);

            $atribuido += $quota;
            $linhas[$especie]['custo_rateado'] += $quota;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $linhas
     * @return array<string, float>
     */
    private function pesos(array $linhas): array
    {
        foreach (['kg', 'area_ha'] as $base) {
            $pesos = array_map(fn (array $linha) => (float) $linha[$base], $linhas);

            if (array_sum($pesos) > 0) {
                return $pesos;
            }
        }

        return array_map(fn () => 1.0, $linhas);
    }

    /**
     * @param  array<string, array<string, mixed>>  $linhas
     * @return array<int, array<string, mixed>>
     */
    private function ordenar(array $linhas): array
    {
        $completas = array_map(fn (array $linha) => $this->completar($linha), array_values($linhas));

        usort($completas, function (array $a, array $b) {
            // "Sem espécie" vai sempre para o fim: é a linha a corrigir, não a ler.
            if (($a['especie'] === self::SEM_ESPECIE) !== ($b['especie'] === self::SEM_ESPECIE)) {
                return $a['especie'] === self::SEM_ESPECIE ? 1 : -1;
            }

            return $b['custo_total'] <=> $a['custo_total'];
        });

        return $completas;
    }

    /** @param array<string, mixed> $linha */
    private function completar(array $linha): array
    {
        $custoTotal = round(
            $linha['custo_operacoes'] + $linha['custo_produtos'] + $linha['custo_diretos'] + $linha['custo_rateado'],
            2
        );

        $kg = round((float) $linha['kg'], 2);
        $kgVendidos = round((float) $linha['kg_vendidos'], 2);
        $vendas = round((float) $linha['vendas'], 2);

        return [
            ...$linha,
            'area_ha' => round((float) $linha['area_ha'], 2),
            'kg' => $kg,
            'kg_vendidos' => $kgVendidos,
            'vendas' => $vendas,
            'custo_operacoes' => round((float) $linha['custo_operacoes'], 2),
            'custo_produtos' => round((float) $linha['custo_produtos'], 2),
            'custo_diretos' => round((float) $linha['custo_diretos'], 2),
            'custo_rateado' => round((float) $linha['custo_rateado'], 2),
            'custo_total' => $custoTotal,
            'custo_kg' => $kg > 0 ? round($custoTotal / $kg, 4) : null,
            'preco_medio_kg' => $kgVendidos > 0 ? round($vendas / $kgVendidos, 4) : null,
            'margem' => round($vendas - $custoTotal, 2),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private function totalizar(array $linhas): array
    {
        $soma = [
            'especie' => 'Total',
            'tratamentos' => 0,
            'area_ha' => 0.0,
            'kg' => 0.0,
            'kg_vendidos' => 0.0,
            'vendas' => 0.0,
            'custo_operacoes' => 0.0,
            'custo_produtos' => 0.0,
            'custo_diretos' => 0.0,
            'custo_rateado' => 0.0,
        ];

        foreach ($linhas as $linha) {
            foreach (array_keys($soma) as $campo) {
                if ($campo === 'especie') {
                    continue;
                }

                $soma[$campo] += $linha[$campo];
            }
        }

        return $this->completar($soma);
    }
}
