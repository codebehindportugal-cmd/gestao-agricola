<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\Custo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reparte pelos campanhas os custos que nao pertencem a nenhuma.
 *
 * A luz das regas, o frio das camaras, o IMI ou o seguro sao gastos da
 * exploracao inteira: pagam-se de uma vez e servem varias campanhas ao mesmo
 * tempo. Ficam registados como Custo sem campanha_id e com rateavel = true;
 * este servico calcula quanto de cada um pertence a cada campanha.
 *
 * Base do rateio:
 *   - 'kg'   (por omissao) proporcional aos quilos colhidos na campanha;
 *   - 'area' proporcional a area das parcelas cobertas.
 *
 * Se a base escolhida ainda nao tem valores (campanha em curso, sem colheita
 * registada), cai na outra; se nenhuma tiver, divide em partes iguais - mais
 * vale imputar por igual do que deixar o custo de fora da conta.
 */
class RateioCustosService
{
    private ?Collection $custos = null;

    private ?Collection $campanhas = null;

    /** @var array<int, array<int, float>> custo_id => [campanha_id => quota] */
    private array $quotas = [];

    /** Total dos custos partilhados imputado a esta campanha. */
    public function totalPara(Campanha $campanha): float
    {
        return round(array_sum(array_column($this->detalhePara($campanha), 'valor')), 2);
    }

    /**
     * Linhas do rateio desta campanha, para mostrar no ecra e no PDF de custos.
     *
     * @return array<int, array{id:int,descricao:string,tipo:string,data:?string,valor:float,valor_total:float,base:string,peso:float,peso_total:float}>
     */
    public function detalhePara(Campanha $campanha): array
    {
        $linhas = [];

        foreach ($this->custosPartilhados() as $custo) {
            $quotas = $this->quotasDoCusto($custo);

            if (! isset($quotas[$campanha->id]) || $quotas[$campanha->id]['valor'] <= 0) {
                continue;
            }

            $quota = $quotas[$campanha->id];

            $linhas[] = [
                'id' => $custo->id,
                'descricao' => $custo->descricao,
                'tipo' => $custo->tipo,
                'data' => $custo->data_custo?->format('Y-m-d'),
                'valor' => $quota['valor'],
                'valor_total' => (float) $custo->valor,
                'base' => $quota['base'],
                'peso' => $quota['peso'],
                'peso_total' => $quota['peso_total'],
            ];
        }

        return $linhas;
    }

    /**
     * Como um custo partilhado se reparte, campanha a campanha.
     *
     * @return array<int, array{campanha:string,campanha_id:int,valor:float,base:string}>
     */
    public function distribuicaoDe(Custo $custo): array
    {
        $campanhas = $this->todasAsCampanhas()->keyBy('id');

        return collect($this->quotasDoCusto($custo))
            ->filter(fn (array $quota) => $quota['valor'] > 0)
            ->map(fn (array $quota, int $campanhaId) => [
                'campanha_id' => $campanhaId,
                'campanha' => $campanhas->get($campanhaId)?->nome_completo ?? '—',
                'valor' => $quota['valor'],
                'base' => $quota['base'],
            ])
            ->values()
            ->all();
    }

    /** Esquece o que ja calculou. Usar depois de gravar ou apagar custos. */
    public function esquecer(): void
    {
        $this->custos = null;
        $this->campanhas = null;
        $this->quotas = [];
    }

    /** @return array<int, array{valor:float,base:string,peso:float,peso_total:float}> */
    private function quotasDoCusto(Custo $custo): array
    {
        if (isset($this->quotas[$custo->id])) {
            return $this->quotas[$custo->id];
        }

        $elegiveis = $this->campanhasElegiveis($custo);

        if ($elegiveis->isEmpty()) {
            return $this->quotas[$custo->id] = [];
        }

        $base = in_array($custo->base_rateio, Custo::BASES_RATEIO, true)
            ? $custo->base_rateio
            : 'kg';

        [$pesos, $baseUsada] = $this->pesos($elegiveis, $base);
        $pesoTotal = array_sum($pesos);

        if ($pesoTotal <= 0) {
            return $this->quotas[$custo->id] = [];
        }

        $valor = (float) $custo->valor;
        $quotas = [];
        $atribuido = 0.0;
        $ultima = array_key_last($pesos);

        foreach ($pesos as $campanhaId => $peso) {
            // A ultima leva o resto, para a soma das quotas bater certo com a fatura.
            $quota = $campanhaId === $ultima
                ? round($valor - $atribuido, 2)
                : round($valor * $peso / $pesoTotal, 2);

            $atribuido += $quota;

            $quotas[$campanhaId] = [
                'valor' => $quota,
                'base' => $baseUsada,
                'peso' => round($peso, 3),
                'peso_total' => round($pesoTotal, 3),
            ];
        }

        return $this->quotas[$custo->id] = $quotas;
    }

    /**
     * @return array{0: array<int,float>, 1: string}
     */
    private function pesos(Collection $campanhas, string $base): array
    {
        $porKg = $campanhas->mapWithKeys(fn (Campanha $c) => [
            $c->id => (float) $c->colheitas->sum('quantidade_total'),
        ])->all();

        $porArea = $campanhas->mapWithKeys(fn (Campanha $c) => [
            $c->id => (float) $c->area_total_ha,
        ])->all();

        $ordem = $base === 'area'
            ? [['area', $porArea], ['kg', $porKg]]
            : [['kg', $porKg], ['area', $porArea]];

        foreach ($ordem as [$nome, $pesos]) {
            if (array_sum($pesos) > 0) {
                return [$pesos, $nome];
            }
        }

        return [$campanhas->mapWithKeys(fn (Campanha $c) => [$c->id => 1.0])->all(), 'igual'];
    }

    /** Campanhas cujo periodo cobre a data do custo. */
    private function campanhasElegiveis(Custo $custo): Collection
    {
        $data = $custo->data_custo ? Carbon::parse($custo->data_custo) : null;

        if ($data === null) {
            return collect();
        }

        return $this->todasAsCampanhas()->filter(function (Campanha $campanha) use ($data) {
            if ($campanha->data_inicio && $campanha->data_fim) {
                return $data->betweenIncluded($campanha->data_inicio, $campanha->data_fim);
            }

            if ($campanha->data_inicio) {
                return $data->greaterThanOrEqualTo($campanha->data_inicio);
            }

            return (int) $campanha->ano === (int) $data->year;
        })->values();
    }

    private function custosPartilhados(): Collection
    {
        return $this->custos ??= Custo::query()
            ->partilhados()
            ->orderBy('data_custo')
            ->get();
    }

    private function todasAsCampanhas(): Collection
    {
        return $this->campanhas ??= Campanha::query()
            ->with(['colheitas:id,campanha_id,quantidade_total', 'parcelas', 'cultura.parcela'])
            ->get();
    }
}
