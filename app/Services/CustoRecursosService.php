<?php

namespace App\Services;

use App\Models\Custo;
use App\Models\Operacao;
use App\Models\OperacaoRecurso;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Maquinas e transporte de uma operacao: grava as linhas, calcula o que
 * custaram e mantem os Custos correspondentes.
 *
 * Existe porque uma operacao so tinha lugar para uma maquina e uma alfaia. Uma
 * apanha com dois tratores, dois empilhadores de campo e um carro a transportar
 * fruta ficava com o custo da mao de obra e mais nada, e o custo/kg da campanha
 * saia errado por defeito.
 */
class CustoRecursosService
{
    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    /**
     * Substitui os recursos de uma operacao pelas linhas indicadas e refaz os
     * custos. Devolve as linhas gravadas, o total e os avisos.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @param  int  $diasOperacao  dias de trabalho, para as linhas que dao horas_por_dia
     * @param  bool  $definirPrincipal  atualizar maquina_id/alfaia_id pela primeira linha
     * @param  float|null  $custoBase  custos da operacao que nao estao nas linhas nem em
     *                                 registos de Custo (o valor escrito a mao no formulario)
     * @return array{recursos: Collection<int, OperacaoRecurso>, total: float, avisos: array<int, string>}
     */
    public function sincronizar(
        Operacao $operacao,
        array $linhas,
        int $diasOperacao = 1,
        bool $definirPrincipal = true,
        ?float $custoBase = null,
    ): array {
        $avisos = [];

        // Substituicao total: reenviar o pedido corrige em vez de duplicar.
        $this->limpar($operacao);

        $recursos = collect();

        foreach ($linhas as $indice => $linha) {
            $recurso = $this->criarRecurso($operacao, $linha, $diasOperacao, $indice, $avisos);
            $recursos->push($recurso);
        }

        $total = round((float) $recursos->sum(fn (OperacaoRecurso $recurso) => (float) $recurso->custo_total), 2);

        $this->registarCustos($operacao, $recursos);

        if ($definirPrincipal) {
            $this->sincronizarPrincipal($operacao, $recursos);
        }

        $this->atualizarCustoOperacao($operacao, $custoBase, $total);

        return ['recursos' => $recursos, 'total' => $total, 'avisos' => $avisos];
    }

    /** Apaga os recursos da operacao e os custos que eles tinham gerado. */
    public function limpar(Operacao $operacao): void
    {
        Custo::query()
            ->where('operacao_id', $operacao->id)
            ->where('tipo', 'maquina')
            ->where('referencia_externa', 'like', $this->prefixoReferencia($operacao).'%')
            ->forceDelete();

        OperacaoRecurso::query()->where('operacao_id', $operacao->id)->delete();
    }

    /**
     * @param  array<string, mixed>  $linha
     * @param  array<int, string>  $avisos
     */
    private function criarRecurso(
        Operacao $operacao,
        array $linha,
        int $diasOperacao,
        int $indice,
        array &$avisos
    ): OperacaoRecurso {
        $maquina = $this->resolverOpcional('resolverMaquina', $linha['maquina'] ?? null);
        $alfaia = $this->resolverOpcional('resolverAlfaia', $linha['alfaia'] ?? null);
        $nome = $this->texto($linha['nome'] ?? null);

        if ($maquina === null && $alfaia === null && $nome === null) {
            throw ValidationException::withMessages([
                "maquinas.{$indice}" => ['Indique maquina, alfaia ou nome para o recurso.'],
            ]);
        }

        $unidades = max(1, (int) ($linha['unidades'] ?? 1));
        $dias = (int) ($linha['dias'] ?? $diasOperacao);
        $horas = $this->horas($linha, $dias);
        $km = $this->numeroOuNulo($linha['km'] ?? null);

        // O custo/hora da linha ganha ao do cadastro: um trator alugado a
        // terceiros nao custa o mesmo que o da casa.
        $custoHora = $this->numeroOuNulo($linha['custo_hora'] ?? null)
            ?? $this->numeroOuNulo($maquina?->custo_hora)
            ?? $this->numeroOuNulo($alfaia?->custo_hora);

        $custoKm = $this->numeroOuNulo($linha['custo_km'] ?? null)
            ?? $this->numeroOuNulo($maquina?->custo_km);

        $recurso = new OperacaoRecurso([
            'operacao_id' => $operacao->id,
            'maquina_id' => $maquina?->id,
            'alfaia_id' => $alfaia?->id,
            'nome' => $nome,
            'papel' => $this->texto($linha['papel'] ?? null),
            'unidades' => $unidades,
            'horas' => $horas,
            'km' => $km,
            'custo_hora' => $custoHora,
            'custo_km' => $custoKm,
            'observacoes' => $this->texto($linha['observacoes'] ?? null),
        ]);

        $custoIndicado = $this->numeroOuNulo($linha['custo_total'] ?? null);
        $recurso->custo_total = $custoIndicado ?? $recurso->calcularCusto();

        $recurso->save();

        if ((float) $recurso->custo_total <= 0.0) {
            $avisos[] = sprintf(
                '%s entrou a zero: sem custo_hora nem custo_km definidos (no recurso ou no cadastro).',
                $recurso->descricao
            );
        }

        return $recurso;
    }

    /**
     * Horas totais da linha: as indicadas, ou horas_por_dia x dias. O numero de
     * unidades entra no calculo do custo, nao aqui, para as horas continuarem a
     * ler-se como horas de uma maquina.
     *
     * @param  array<string, mixed>  $linha
     */
    private function horas(array $linha, int $dias): ?float
    {
        $horas = $this->numeroOuNulo($linha['horas'] ?? null);

        if ($horas !== null) {
            return $horas;
        }

        $horasPorDia = $this->numeroOuNulo($linha['horas_por_dia'] ?? null);

        if ($horasPorDia === null) {
            return null;
        }

        return round($horasPorDia * max(1, $dias), 2);
    }

    /**
     * Um Custo por recurso, para depois se poder perguntar quanto custou cada
     * maquina no ano. A referencia_externa e deterministica, para que refazer a
     * operacao substitua os custos em vez de os somar.
     *
     * @param  Collection<int, OperacaoRecurso>  $recursos
     */
    private function registarCustos(Operacao $operacao, Collection $recursos): void
    {
        $data = $operacao->data_hora_inicio?->toDateString() ?? now()->toDateString();

        foreach ($recursos as $recurso) {
            if ((float) $recurso->custo_total <= 0.0) {
                continue;
            }

            Custo::query()->create([
                'descricao' => $this->descricaoCusto($operacao, $recurso),
                'tipo' => 'maquina',
                'valor' => $recurso->custo_total,
                'data_custo' => $data,
                'operacao_id' => $operacao->id,
                'campanha_id' => $operacao->campanha_id,
                'cultura_id' => $operacao->cultura_id,
                'parcela_id' => $operacao->parcela_id,
                'maquina_id' => $recurso->maquina_id,
                'referencia_externa' => $this->prefixoReferencia($operacao).$recurso->id,
            ]);
        }
    }

    private function descricaoCusto(Operacao $operacao, OperacaoRecurso $recurso): string
    {
        $detalhe = [];

        if ($recurso->horas !== null) {
            $detalhe[] = rtrim(rtrim(number_format((float) $recurso->horas, 2, ',', ''), '0'), ',').'h';
        }

        if ($recurso->km !== null) {
            $detalhe[] = rtrim(rtrim(number_format((float) $recurso->km, 2, ',', ''), '0'), ',').' km';
        }

        $descricao = $operacao->tipo.' - '.$recurso->descricao;

        return $detalhe === [] ? $descricao : $descricao.' ('.implode(', ', $detalhe).')';
    }

    /**
     * A primeira linha alimenta maquina_id/alfaia_id da operacao. O caderno de
     * campo DGAV e os ecras antigos leem essas colunas e nao podem ficar vazios
     * so porque a operacao passou a ter varias maquinas.
     *
     * @param  Collection<int, OperacaoRecurso>  $recursos
     */
    private function sincronizarPrincipal(Operacao $operacao, Collection $recursos): void
    {
        $principal = $recursos->first(fn (OperacaoRecurso $recurso) => $recurso->maquina_id !== null)
            ?? $recursos->first(fn (OperacaoRecurso $recurso) => $recurso->alfaia_id !== null);

        if ($principal === null) {
            return;
        }

        $operacao->forceFill([
            'maquina_id' => $principal->maquina_id ?? $operacao->maquina_id,
            'alfaia_id' => $principal->alfaia_id ?? $operacao->alfaia_id,
        ])->save();
    }

    /**
     * custo_real da operacao.
     *
     * Sem custo base (ingestao pela API): e a soma de tudo o que esta ligado a
     * operacao - mao de obra e maquinas -, para que o valor da coluna e a soma
     * dos Custos digam o mesmo e a Campanha nao tenha de escolher entre eles.
     *
     * Com custo base (formulario): o utilizador escreveu a mao o que nao esta
     * nas linhas de recursos, e o custo real e esse valor mais as maquinas.
     * Somar em vez de recalcular e o que impede o formulario de apagar um
     * valor escrito a mao quando se acrescenta um trator.
     */
    private function atualizarCustoOperacao(Operacao $operacao, ?float $custoBase, float $totalRecursos): void
    {
        $total = $custoBase === null
            ? round((float) Custo::query()->where('operacao_id', $operacao->id)->sum('valor'), 2)
            : round($custoBase + $totalRecursos, 2);

        $operacao->forceFill([
            'custo_real' => $total,
            // No formulario o estimado e do utilizador; na ingestao nao ha
            // estimativa nenhuma e o real serve de estimativa.
            'custo_estimado' => $custoBase === null ? $total : $operacao->custo_estimado,
        ])->save();
    }

    private function prefixoReferencia(Operacao $operacao): string
    {
        return 'op-'.$operacao->id.'-recurso-';
    }

    private function resolverOpcional(string $metodo, mixed $referencia): ?object
    {
        if ($referencia === null || $referencia === '') {
            return null;
        }

        return $this->resolvedor->{$metodo}($this->valorReferencia($referencia));
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'nome', 'codigo', 'matricula'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }

    private function numeroOuNulo(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
