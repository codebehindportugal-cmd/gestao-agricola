<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTrabalhoApiRequest;
use App\Http\Resources\Api\V1\OperacaoResource;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Funcionario;
use App\Models\Jornada;
use App\Models\Operacao;
use App\Services\CustoRecursosService;
use App\Services\ResolvedorReferencias;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingestao de trabalho / mao de obra.
 *
 * Cria uma Operacao e uma Jornada por funcionario e por dia trabalhado,
 * mais um Custo agregado de tipo mao_obra (o que a tesouraria contabiliza).
 *
 * As maquinas, alfaias e viaturas usadas vao em maquinas[], cada uma com as
 * suas horas ou km; o CustoRecursosService transforma-as em Custos tipo
 * maquina. Sem isso uma apanha ficava registada so com a mao de obra.
 */
class TrabalhoController extends Controller
{
    use RespondeJson;

    private const MAX_JORNADAS = 2000;

    public function __construct(
        private readonly ResolvedorReferencias $resolvedor,
        private readonly CustoRecursosService $custoRecursos,
    ) {
    }

    public function store(StoreTrabalhoApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avisos = [];

        if (! empty($data['referencia_externa'])) {
            $existente = Operacao::query()
                ->where('referencia_externa', $data['referencia_externa'])
                ->first();

            if ($existente) {
                $avisos[] = "trabalho ja registado ({$data['referencia_externa']})";

                return $this->criado([
                    'operacao' => OperacaoResource::make($existente->load(['campanha', 'parcela', 'cultura']))->resolve(),
                    'jornadas' => Jornada::query()->where('operacao_id', $existente->id)->count(),
                ], $avisos);
            }
        }

        try {
            $dias = $this->diasTrabalhados($data);
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        if ($dias === []) {
            return $this->erro422([
                'data_inicio' => ['O periodo indicado nao contem nenhum dia de trabalho.'],
            ]);
        }

        try {
            [$operacao, $jornadas, $custo, $recursos, $avisosCriacao] = DB::transaction(function () use ($data, $dias) {
                $avisos = [];

                $campanha = $this->resolverOpcional('resolverCampanha', $data['campanha'] ?? null);
                $parcela = $this->resolverOpcional('resolverParcela', $data['parcela'] ?? null);
                $maquina = $this->resolverOpcional('resolverMaquina', $data['maquina'] ?? null);
                $alfaia = $this->resolverOpcional('resolverAlfaia', $data['alfaia'] ?? null);
                $equipa = $this->resolverOpcional('resolverEquipa', $data['equipa'] ?? null);

                $cultura = $this->resolverCultura($data['cultura'] ?? null, $parcela?->id);

                /** @var Collection<int, Funcionario> $funcionarios */
                $funcionarios = $this->reunirFuncionarios($data, $equipa);

                $numeroPessoas = $funcionarios->isNotEmpty()
                    ? $funcionarios->count()
                    : (int) ($data['numero_pessoas'] ?? 0);

                if ($numeroPessoas < 1) {
                    throw ValidationException::withMessages([
                        'numero_pessoas' => ['Nao foi possivel determinar quantas pessoas trabalharam.'],
                    ]);
                }

                if (isset($data['numero_pessoas']) && $funcionarios->isNotEmpty()
                    && (int) $data['numero_pessoas'] !== $funcionarios->count()) {
                    $avisos[] = sprintf(
                        'numero_pessoas (%d) difere dos funcionarios identificados (%d); foram usados os funcionarios.',
                        (int) $data['numero_pessoas'],
                        $funcionarios->count()
                    );
                }

                $horasPorDia = (float) $data['horas_por_dia'];
                $totalHoras = round($numeroPessoas * count($dias) * $horasPorDia, 2);

                if ($funcionarios->count() * count($dias) > self::MAX_JORNADAS) {
                    throw ValidationException::withMessages([
                        'funcionarios' => [sprintf(
                            'O pedido geraria %d jornadas (limite %d). Divida o periodo em varios pedidos.',
                            $funcionarios->count() * count($dias),
                            self::MAX_JORNADAS
                        )],
                    ]);
                }

                $tipo = $data['tipo'] ?? 'colheita';

                $operacao = Operacao::query()->create([
                    'campanha_id' => $campanha?->id,
                    'parcela_id' => $parcela?->id,
                    'cultura_id' => $cultura?->id,
                    'tipo' => $tipo,
                    'data_hora_inicio' => $dias[0]->toDateString().' 00:00:00',
                    'data_hora_fim' => end($dias)->toDateString().' 23:59:59',
                    'maquina_id' => $maquina?->id,
                    'alfaia_id' => $alfaia?->id,
                    'equipa_id' => $equipa?->id,
                    'funcionario_id' => $funcionarios->count() === 1 ? $funcionarios->first()->id : null,
                    'duracao_horas' => $totalHoras,
                    'referencia_externa' => $data['referencia_externa'] ?? null,
                    'observacoes' => $data['observacoes'] ?? null,
                    'estado' => 'concluida',
                ]);

                // Jornadas: detalhe por pessoa e por dia (so para funcionarios registados).
                $jornadas = 0;
                $custoCalculado = 0.0;
                $semValorHora = [];

                foreach ($funcionarios as $funcionario) {
                    $valorHora = $this->floatOuNulo($funcionario->valor_hora)
                        ?? $this->floatOuNulo($data['valor_hora'] ?? null);

                    if ($valorHora === null) {
                        $semValorHora[] = $funcionario->nome;
                    }

                    foreach ($dias as $dia) {
                        Jornada::query()->create([
                            'funcionario_id' => $funcionario->id,
                            'operacao_id' => $operacao->id,
                            'data' => $dia->toDateString(),
                            'horas_trabalhadas' => $horasPorDia,
                            'tarefa' => $data['tarefa'],
                        ]);
                        $jornadas++;
                    }

                    $custoCalculado += ($valorHora ?? 0.0) * $horasPorDia * count($dias);
                }

                if ($semValorHora !== []) {
                    $avisos[] = 'sem valor_hora definido para: '.implode(', ', $semValorHora)
                        .'; essas horas entraram no custo a zero.';
                }

                if ($funcionarios->isEmpty()) {
                    $avisos[] = 'nenhum funcionario registado indicado; nao foram criadas jornadas, so a operacao e o custo agregado.';

                    $valorHora = $this->floatOuNulo($data['valor_hora'] ?? null);
                    $custoCalculado = $valorHora === null
                        ? 0.0
                        : $valorHora * $horasPorDia * count($dias) * $numeroPessoas;
                }

                $valorCusto = $this->floatOuNulo($data['custo_total'] ?? null) ?? round($custoCalculado, 2);

                if ($valorCusto <= 0.0) {
                    $avisos[] = 'custo nao calculado (sem valor_hora nem custo_total); nao foi criado registo de custo.';
                    $custo = null;
                } else {
                    $custo = Custo::query()->create([
                        'descricao' => $data['tarefa'].' - mao de obra ('.$numeroPessoas.' pessoas x '
                            .count($dias).' dias x '.$horasPorDia.'h)',
                        'tipo' => 'mao_obra',
                        'valor' => $valorCusto,
                        'data_custo' => $dias[0]->toDateString(),
                        'operacao_id' => $operacao->id,
                        'campanha_id' => $campanha?->id,
                        'cultura_id' => $cultura?->id,
                        'parcela_id' => $parcela?->id,
                        'referencia_externa' => isset($data['referencia_externa'])
                            ? $data['referencia_externa'].'-mao-obra'
                            : null,
                    ]);

                    $operacao->update([
                        'custo_real' => $valorCusto,
                        'custo_estimado' => $valorCusto,
                    ]);
                }

                // Maquinas, alfaias e transporte. A maquina/alfaia singulares
                // continuam a valer: entram como mais uma linha.
                $linhasRecursos = $this->linhasRecursos($data, $maquina?->id, $alfaia?->id);
                $recursos = collect();

                if ($linhasRecursos !== []) {
                    $resultado = $this->custoRecursos->sincronizar($operacao, $linhasRecursos, count($dias));

                    $recursos = $resultado['recursos'];
                    $avisos = array_merge($avisos, $resultado['avisos']);
                }

                $this->ligarColheita($data['colheita'] ?? null, $operacao, $avisos);

                $operacao = $operacao->fresh()->load([
                    'campanha', 'parcela', 'cultura', 'recursos.maquina', 'recursos.alfaia',
                ]);

                return [$operacao, $jornadas, $custo, $recursos, $avisos];
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado([
            'operacao' => OperacaoResource::make($operacao)->resolve(),
            'dias_trabalhados' => count($dias),
            'jornadas' => $jornadas,
            'custo' => $custo === null ? null : [
                'id' => $custo->id,
                'tipo' => $custo->tipo,
                'valor' => $custo->valor,
                'data' => $custo->data_custo?->toDateString() ?? (string) $custo->data_custo,
            ],
            'recursos' => $recursos->map(fn ($recurso) => [
                'id' => $recurso->id,
                'descricao' => $recurso->descricao,
                'unidades' => $recurso->unidades,
                'horas' => $recurso->horas,
                'km' => $recurso->km,
                'custo_hora' => $recurso->custo_hora,
                'custo_km' => $recurso->custo_km,
                'custo_total' => $recurso->custo_total,
            ])->values(),
            'custo_mao_obra' => $custo?->valor ?? 0,
            'custo_maquinas' => round((float) $recursos->sum(fn ($recurso) => (float) $recurso->custo_total), 2),
            'custo_total' => $operacao->custo_real,
        ], array_merge($avisos, $avisosCriacao));
    }

    /**
     * As linhas de recursos do pedido, juntando a maquina/alfaia singulares
     * (formato antigo) ao array maquinas[].
     *
     * Uma linha sem horas nem km herda as horas por dia da mao de obra: a
     * maquina andou o mesmo tempo que a equipa, que e o caso normal.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function linhasRecursos(array $data, ?int $maquinaId, ?int $alfaiaId): array
    {
        $linhas = [];

        foreach ($data['maquinas'] ?? [] as $linha) {
            $temMedida = isset($linha['horas']) || isset($linha['horas_por_dia'])
                || isset($linha['km']) || isset($linha['custo_total']);

            if (! $temMedida) {
                $linha['horas_por_dia'] = $data['horas_por_dia'];
            }

            $linhas[] = $linha;
        }

        // So se o pedido nao trouxer maquinas[]: com as duas listas, a singular
        // e um resumo da outra e duplicava o custo.
        if ($linhas === [] && ($maquinaId !== null || $alfaiaId !== null)) {
            $linhas[] = array_filter([
                'maquina' => $maquinaId,
                'alfaia' => $alfaiaId,
                'horas_por_dia' => $data['horas_por_dia'],
            ], fn ($valor) => $valor !== null);
        }

        return $linhas;
    }

    /**
     * Liga a colheita indicada a esta operacao, para a colheita saber o que a
     * apanha custou. Nao mexe numa colheita que ja tenha operacao.
     *
     * @param  array<int, string>  $avisos
     */
    private function ligarColheita(mixed $referencia, Operacao $operacao, array &$avisos): void
    {
        if ($referencia === null || $referencia === '') {
            return;
        }

        /** @var Colheita $colheita */
        $colheita = $this->resolvedor->resolverColheita($this->valorReferencia($referencia));

        if ($colheita->operacao_id !== null && $colheita->operacao_id !== $operacao->id) {
            $avisos[] = sprintf(
                'colheita #%d ja estava ligada a operacao #%d; nao foi alterada.',
                $colheita->id,
                $colheita->operacao_id
            );

            return;
        }

        $colheita->forceFill(['operacao_id' => $operacao->id])->save();
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function diasTrabalhados(array $data): array
    {
        $inicio = CarbonImmutable::parse($data['data_inicio'])->startOfDay();

        if (! empty($data['data_fim'])) {
            $fim = CarbonImmutable::parse($data['data_fim'])->startOfDay();
        } elseif (! empty($data['dias'])) {
            $fim = $inicio->addDays((int) $data['dias'] - 1);
        } else {
            $fim = $inicio->addDays((int) round(((float) $data['semanas']) * 7) - 1);
        }

        if ($fim->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'data_fim' => ['data_fim anterior a data_inicio.'],
            ]);
        }

        if ($inicio->diffInDays($fim) > 366) {
            throw ValidationException::withMessages([
                'data_fim' => ['Periodo superior a um ano; divida em varios pedidos.'],
            ]);
        }

        $incluirFds = (bool) ($data['incluir_fins_de_semana'] ?? false);
        $dias = [];

        for ($dia = $inicio; $dia->lessThanOrEqualTo($fim); $dia = $dia->addDay()) {
            if (! $incluirFds && $dia->isWeekend()) {
                continue;
            }

            $dias[] = $dia;
        }

        return $dias;
    }

    /**
     * @return Collection<int, Funcionario>
     */
    private function reunirFuncionarios(array $data, ?object $equipa): Collection
    {
        $funcionarios = collect();

        if ($equipa !== null) {
            $funcionarios = $funcionarios->concat($equipa->funcionarios);
        }

        foreach ($data['funcionarios'] ?? [] as $referencia) {
            $funcionarios->push($this->resolvedor->resolverFuncionario($this->valorReferencia($referencia)));
        }

        return $funcionarios->unique('id')->values();
    }

    private function resolverCultura(mixed $referencia, ?int $parcelaId): ?Cultura
    {
        if ($referencia !== null && $referencia !== '') {
            return $this->resolvedor->resolverCultura($this->valorReferencia($referencia));
        }

        if ($parcelaId === null) {
            return null;
        }

        return Cultura::query()->where('parcela_id', $parcelaId)->orderBy('id')->first();
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

        foreach (['id', 'nome', 'codigo'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }

    private function floatOuNulo(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }
}
