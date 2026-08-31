<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAplicacaoApiRequest;
use App\Http\Resources\Api\V1\OperacaoResource;
use App\Models\Cultura;
use App\Models\Operacao;
use App\Models\Produto;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AplicacaoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(StoreAplicacaoApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avisos = [];

        $entradas = $this->normalizarParcelas($data);
        $multiParcela = ! empty($data['parcelas']);
        $referencias = $this->referenciasExternas($data['referencia_externa'] ?? null, count($entradas));

        if ($referencias !== []) {
            $existentes = Operacao::query()
                ->whereIn('referencia_externa', $referencias)
                ->get();

            if ($existentes->isNotEmpty()) {
                $avisos[] = "aplicacao ja registada ({$data['referencia_externa']})";

                return $this->respostaOperacoes(
                    $existentes->map(fn (Operacao $operacao) => $operacao->load($this->relacoes()))->all(),
                    $avisos
                );
            }
        }

        try {
            [$operacoes, $avisosCriacao] = DB::transaction(function () use ($data, $entradas, $referencias, $multiParcela) {
                $avisos = [];

                $campanha = $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']));
                $maquina = $this->resolverOpcional('resolverMaquina', $data['maquina'] ?? null);
                $alfaia = $this->resolverOpcional('resolverAlfaia', $data['alfaia'] ?? null);
                $funcionario = $this->resolverOpcional('resolverFuncionario', $data['funcionario'] ?? null);
                $equipa = $this->resolverOpcional('resolverEquipa', $data['equipa'] ?? null);

                $pesos = $this->pesosDistribuicao($entradas);

                if (count($entradas) > 1 && ($data['duracao_horas'] ?? null) !== null) {
                    $avisos[] = $pesos['proporcional']
                        ? 'duracao_horas e combustivel_gasto_l distribuidos pelas parcelas proporcionalmente a area_tratada'
                        : 'duracao_horas e combustivel_gasto_l distribuidos igualmente pelas parcelas';
                }

                $operacoes = [];

                foreach ($entradas as $indice => $entrada) {
                    $parcela = $this->resolvedor->resolverParcela($this->valorReferencia($entrada['parcela']));
                    $cultura = $this->resolverCultura(
                        $entrada['cultura'] ?? $data['cultura'] ?? null,
                        $parcela->id
                    );

                    $linhas = $entrada['produtos'] ?? $data['produtos'] ?? [];

                    $prefixo = $multiParcela ? "parcelas.{$indice}.produtos" : 'produtos';

                    if ($linhas === []) {
                        throw ValidationException::withMessages([
                            $prefixo => ['Nenhum produto indicado para esta parcela.'],
                        ]);
                    }

                    $produtos = $this->formatarProdutos($linhas, $entrada, $prefixo);

                    $operacao = Operacao::query()->create([
                        'campanha_id' => $campanha->id,
                        'parcela_id' => $parcela->id,
                        'cultura_id' => $cultura?->id,
                        'tipo' => $data['tipo'] ?? 'tratamento fitossanitário',
                        'data_hora_inicio' => $data['data'].' 00:00:00',
                        'data_hora_fim' => isset($data['data_fim']) ? $data['data_fim'].' 00:00:00' : null,
                        'maquina_id' => $maquina?->id,
                        'alfaia_id' => $alfaia?->id,
                        'funcionario_id' => $funcionario?->id,
                        'equipa_id' => $equipa?->id,
                        'duracao_horas' => $this->fatia(
                            $entrada['duracao_horas'] ?? null,
                            $data['duracao_horas'] ?? null,
                            $pesos['pesos'][$indice]
                        ),
                        'distancia_km' => $this->fatia(
                            null,
                            $data['distancia_km'] ?? null,
                            $pesos['pesos'][$indice]
                        ),
                        'combustivel_gasto_l' => $this->fatia(
                            $entrada['combustivel_gasto_l'] ?? null,
                            $data['combustivel_gasto_l'] ?? null,
                            $pesos['pesos'][$indice]
                        ),
                        'produtor_nome' => $data['produtor_nome'] ?? null,
                        'aplicador_nome' => $data['aplicador_nome'] ?? null,
                        'aplicador_numero_autorizacao' => $data['aplicador_numero_autorizacao'] ?? null,
                        'exploracao_concelho' => $data['exploracao_concelho'] ?? null,
                        'exploracao_freguesia' => $data['exploracao_freguesia'] ?? null,
                        'custo_estimado' => $this->fatia(null, $data['custo_estimado'] ?? null, $pesos['pesos'][$indice]),
                        'custo_real' => $this->fatia(null, $data['custo_real'] ?? null, $pesos['pesos'][$indice]),
                        'referencia_externa' => $referencias[$indice] ?? null,
                        'observacoes' => $entrada['observacoes'] ?? $data['observacoes'] ?? null,
                        'estado' => 'concluida',
                    ]);

                    $operacao->produtos()->attach($produtos);

                    $operacoes[] = $operacao->load($this->relacoes());
                }

                return [$operacoes, $avisos];
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->respostaOperacoes($operacoes, array_merge($avisos, $avisosCriacao));
    }

    /**
     * Normaliza o payload para uma lista de entradas por parcela.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizarParcelas(array $data): array
    {
        if (! empty($data['parcelas'])) {
            return array_values(array_map(
                fn (mixed $entrada) => is_array($entrada) && array_key_exists('parcela', $entrada)
                    ? $entrada
                    : ['parcela' => $entrada],
                $data['parcelas']
            ));
        }

        return [['parcela' => $data['parcela']]];
    }

    /**
     * Uma referencia por operacao. Com varias parcelas acrescenta sufixo -1, -2, ...
     *
     * @return array<int, string>
     */
    private function referenciasExternas(?string $base, int $total): array
    {
        if ($base === null || $base === '') {
            return [];
        }

        if ($total === 1) {
            return [$base];
        }

        return array_map(fn (int $i) => $base.'-'.($i + 1), range(0, $total - 1));
    }

    /**
     * Peso de cada parcela na distribuicao de horas/combustivel/custos.
     *
     * @return array{pesos: array<int, float>, proporcional: bool}
     */
    private function pesosDistribuicao(array $entradas): array
    {
        $total = count($entradas);
        $areas = array_map(fn (array $e) => $this->floatOuNulo($e['area_tratada'] ?? null), $entradas);
        $soma = array_sum(array_map(fn (?float $a) => $a ?? 0.0, $areas));

        if ($soma > 0 && ! in_array(null, $areas, true)) {
            return [
                'pesos' => array_map(fn (?float $a) => (float) $a / $soma, $areas),
                'proporcional' => true,
            ];
        }

        return [
            'pesos' => array_fill(0, $total, 1 / max($total, 1)),
            'proporcional' => false,
        ];
    }

    /**
     * Valor especifico da parcela; senao a fatia do total indicado no topo.
     */
    private function fatia(mixed $especifico, mixed $total, float $peso): ?float
    {
        $valorEspecifico = $this->floatOuNulo($especifico);

        if ($valorEspecifico !== null) {
            return $valorEspecifico;
        }

        $valorTotal = $this->floatOuNulo($total);

        if ($valorTotal === null) {
            return null;
        }

        return round($valorTotal * $peso, 2);
    }

    private function resolverOpcional(string $metodo, mixed $referencia): ?object
    {
        if ($referencia === null || $referencia === '') {
            return null;
        }

        return $this->resolvedor->{$metodo}($this->valorReferencia($referencia));
    }

    private function resolverCultura(mixed $referencia, int $parcelaId): ?Cultura
    {
        if ($referencia !== null && $referencia !== '') {
            return $this->resolvedor->resolverCultura($this->valorReferencia($referencia));
        }

        return Cultura::query()
            ->where('parcela_id', $parcelaId)
            ->orderBy('id')
            ->first();
    }

    private function formatarProdutos(array $linhas, array $entrada, string $prefixo): array
    {
        $produtos = [];

        foreach ($linhas as $indice => $linha) {
            $produto = $this->resolvedor->resolverProduto($this->valorReferencia($linha['produto']));
            $this->validarConformidadeDgav($produto, $prefixo, $indice);

            $areaTratada = $this->floatOuNulo($linha['area_tratada'] ?? null)
                ?? $this->floatOuNulo($entrada['area_tratada'] ?? null);
            $volumeCalda = $this->floatOuNulo($linha['volume_calda'] ?? null)
                ?? $this->floatOuNulo($entrada['volume_calda'] ?? null);

            $quantidade = $this->floatOuNulo($linha['quantidade'] ?? null);
            $dose = $this->floatOuNulo($linha['dose'] ?? null);

            // Sem quantidade explicita, deriva-a da dose (por ha ou por volume de calda).
            if ($quantidade === null && $dose !== null) {
                $unidade = strtolower((string) ($linha['dose_unidade'] ?? ''));

                if (str_contains($unidade, '/ha') && $areaTratada !== null) {
                    $quantidade = round($dose * $areaTratada, 4);
                } elseif (str_contains($unidade, '/hl') && $volumeCalda !== null) {
                    $quantidade = round($dose * ($volumeCalda / 100), 4);
                } elseif (str_contains($unidade, '/1000l') && $volumeCalda !== null) {
                    $quantidade = round($dose * ($volumeCalda / 1000), 4);
                }
            }

            $quantidade ??= 0.0;
            $custoUnitario = $this->floatOuNulo($linha['custo_unitario'] ?? null) ?? $this->floatOuNulo($produto->custo_unitario);

            $produtos[$produto->id] = [
                'quantidade' => $quantidade,
                'unidade_medida' => $produto->unidade_medida ?: 'un',
                'dose' => $dose,
                'dose_unidade' => $linha['dose_unidade'] ?? null,
                'area_tratada' => $areaTratada,
                'volume_calda' => $volumeCalda,
                'finalidade' => $linha['finalidade'] ?? null,
                'intervalo_seguranca_dias' => $linha['intervalo_seguranca_dias'] ?? null,
                'estabelecimento_venda_nome' => $linha['estabelecimento_venda_nome'] ?? null,
                'estabelecimento_venda_autorizacao' => $linha['estabelecimento_venda_autorizacao'] ?? null,
                'custo_unitario' => $custoUnitario,
                'custo_total' => $custoUnitario === null ? null : round($quantidade * $custoUnitario, 2),
                'observacoes' => $linha['observacoes'] ?? null,
            ];
        }

        return $produtos;
    }

    private function validarConformidadeDgav(Produto $produto, string $prefixo, int $indice): void
    {
        if ($produto->tipo === 'fitofarmaceutico' && blank($produto->numero_autorizacao_dgav)) {
            throw ValidationException::withMessages([
                "{$prefixo}.{$indice}.produto" => [
                    'Produto fitofarmaceutico sem numero_autorizacao_dgav; registo nao conforme DGAV.',
                ],
            ]);
        }
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'numero_autorizacao_dgav', 'nome', 'codigo'] as $chave) {
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

    /**
     * @param  array<int, Operacao>  $operacoes
     */
    private function respostaOperacoes(array $operacoes, array $avisos): JsonResponse
    {
        $lista = array_map(fn (Operacao $operacao) => OperacaoResource::make($operacao)->resolve(), $operacoes);

        return $this->criado([
            'operacao' => $lista[0] ?? null,
            'operacoes' => $lista,
        ], $avisos);
    }

    private function relacoes(): array
    {
        return ['campanha', 'parcela', 'cultura', 'produtos'];
    }
}
