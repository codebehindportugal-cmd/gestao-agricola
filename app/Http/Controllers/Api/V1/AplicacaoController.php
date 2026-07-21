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

        if (! empty($data['referencia_externa'])) {
            $existente = Operacao::query()
                ->where('referencia_externa', $data['referencia_externa'])
                ->first();

            if ($existente) {
                $avisos[] = "aplicacao ja registada ({$data['referencia_externa']})";

                return $this->criado([
                    'operacao' => OperacaoResource::make($existente->load($this->relacoes()))->resolve(),
                ], $avisos);
            }
        }

        try {
            $operacao = DB::transaction(function () use ($data) {
                $campanha = $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']));
                $parcela = $this->resolvedor->resolverParcela($this->valorReferencia($data['parcela']));
                $cultura = $this->resolverCultura($data['cultura'] ?? null, $parcela->id);
                $produtos = $this->formatarProdutos($data['produtos']);

                $operacao = Operacao::query()->create([
                    'campanha_id' => $campanha->id,
                    'parcela_id' => $parcela->id,
                    'cultura_id' => $cultura?->id,
                    'tipo' => $data['tipo'] ?? 'tratamento',
                    'data_hora_inicio' => $data['data'].' 00:00:00',
                    'produtor_nome' => $data['produtor_nome'] ?? null,
                    'aplicador_nome' => $data['aplicador_nome'] ?? null,
                    'aplicador_numero_autorizacao' => $data['aplicador_numero_autorizacao'] ?? null,
                    'exploracao_concelho' => $data['exploracao_concelho'] ?? null,
                    'exploracao_freguesia' => $data['exploracao_freguesia'] ?? null,
                    'custo_estimado' => $data['custo_estimado'] ?? null,
                    'custo_real' => $data['custo_real'] ?? null,
                    'referencia_externa' => $data['referencia_externa'] ?? null,
                    'observacoes' => $data['observacoes'] ?? null,
                    'estado' => 'concluida',
                ]);

                $operacao->produtos()->attach($produtos);

                return $operacao->load($this->relacoes());
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado([
            'operacao' => OperacaoResource::make($operacao)->resolve(),
        ], $avisos);
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

    private function formatarProdutos(array $linhas): array
    {
        $produtos = [];

        foreach ($linhas as $indice => $linha) {
            $produto = $this->resolvedor->resolverProduto($this->valorReferencia($linha['produto']));
            $this->validarConformidadeDgav($produto, $indice);

            $quantidade = $this->floatOuZero($linha['quantidade'] ?? 0);
            $custoUnitario = $this->floatOuNulo($linha['custo_unitario'] ?? null);

            $produtos[$produto->id] = [
                'quantidade' => $quantidade,
                'unidade_medida' => $produto->unidade_medida ?: 'un',
                'dose' => $this->floatOuNulo($linha['dose'] ?? null),
                'dose_unidade' => $linha['dose_unidade'] ?? null,
                'area_tratada' => $this->floatOuNulo($linha['area_tratada'] ?? null),
                'volume_calda' => $this->floatOuNulo($linha['volume_calda'] ?? null),
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

    private function validarConformidadeDgav(Produto $produto, int $indice): void
    {
        if ($produto->tipo === 'fitofarmaceutico' && blank($produto->numero_autorizacao_dgav)) {
            throw ValidationException::withMessages([
                "produtos.{$indice}.produto" => [
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

    private function floatOuZero(mixed $valor): float
    {
        return $this->floatOuNulo($valor) ?? 0.0;
    }

    private function relacoes(): array
    {
        return ['campanha', 'parcela', 'cultura', 'produtos'];
    }
}
