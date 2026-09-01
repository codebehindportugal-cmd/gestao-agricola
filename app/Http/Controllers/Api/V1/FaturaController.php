<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFaturaApiRequest;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Produto;
use App\Services\MovimentoStockService;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ingestao de faturas de compra.
 *
 * Cria a Despesa e as suas FaturaItem, resolve ou cria os Produtos, da entrada
 * em stock (mesmo servico do ecra de despesas) e cria o Custo correspondente,
 * que e o que a tesouraria contabiliza como saida.
 */
class FaturaController extends Controller
{
    use RespondeJson;

    /** categoria da despesa -> tipo de custo */
    private const CATEGORIA_PARA_TIPO_CUSTO = [
        'combustivel' => 'energia',
        'sementes' => 'material',
        'fertilizantes' => 'material',
        'fitofarmaceuticos' => 'material',
        'equipamento' => 'maquinaria',
        'pecas' => 'manutencao',
        'mao_obra' => 'mao_obra',
        'outro' => 'outro',
    ];

    public function __construct(
        private readonly ResolvedorReferencias $resolvedor,
        private readonly MovimentoStockService $stock
    ) {
    }

    public function store(StoreFaturaApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avisos = [];

        // Uma fatura e identificada pelo numero + fornecedor: nao ha coluna
        // referencia_externa em despesas, e essa combinacao ja e unica na pratica.
        if (! empty($data['numero_fatura'])) {
            $existente = Despesa::query()
                ->where('numero_fatura', $data['numero_fatura'])
                ->when(! empty($data['fornecedor']), fn ($q) => $q->where('fornecedor', $data['fornecedor']))
                ->first();

            if ($existente) {
                $avisos[] = "fatura ja registada ({$data['numero_fatura']})";

                return $this->criado($this->formatar($existente->load('items.produto'), null), $avisos);
            }
        }

        try {
            [$despesa, $custo, $movimentos, $avisosCriacao] = DB::transaction(function () use ($data) {
                $avisos = [];

                $campanha = null;
                $maquina = null;

                if (! empty($data['campanha'])) {
                    $campanha = $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']));
                }

                // Faturas de pecas ligam-se a maquina, para o custo entrar no
                // desgaste daquele tractor e nao num saco geral.
                if (! empty($data['maquina'])) {
                    $maquina = $this->resolvedor->resolverMaquina($this->valorReferencia($data['maquina']));
                }

                $criarProdutos = $data['criar_produtos'] ?? true;
                $actualizarCusto = $data['actualizar_custo_unitario'] ?? true;

                $linhas = [];
                $totalCalculado = 0.0;

                foreach ($data['linhas'] as $indice => $linha) {
                    // O estabelecimento que vendeu o produto e campo do caderno
                    // de campo; por omissao e o fornecedor da propria fatura.
                    $linha['estabelecimento_venda_nome'] ??= $data['fornecedor'] ?? null;

                    $produto = $this->resolverProduto($linha, $criarProdutos, $indice, $avisos);

                    if ($produto !== null) {
                        $actualizacoes = [];

                        if ($actualizarCusto) {
                            $precoUnitario = (float) $linha['preco_unitario'];

                            if ((float) $produto->custo_unitario !== $precoUnitario) {
                                $actualizacoes['custo_unitario'] = $precoUnitario;
                            }
                        }

                        if (! empty($linha['codigo']) && blank($produto->codigo_interno)) {
                            $actualizacoes['codigo_interno'] = $linha['codigo'];
                        }

                        foreach (['estabelecimento_venda_nome', 'estabelecimento_venda_autorizacao'] as $campo) {
                            if (! empty($linha[$campo]) && blank($produto->{$campo})) {
                                $actualizacoes[$campo] = $linha[$campo];
                            }
                        }

                        if ($actualizacoes !== []) {
                            $produto->update($actualizacoes);
                        }
                    }

                    $quantidade = (float) $linha['quantidade'];
                    $preco = (float) $linha['preco_unitario'];
                    $iva = (float) ($linha['iva_percentagem'] ?? 0);
                    $totalCalculado += $quantidade * $preco * (1 + $iva / 100);

                    $linhas[] = [
                        'descricao' => $linha['descricao'],
                        'quantidade' => $quantidade,
                        'preco_unitario' => $preco,
                        'iva_percentagem' => $iva,
                        'produto_id' => $produto?->id,
                        'notas' => $linha['notas'] ?? null,
                    ];
                }

                $totalCalculado = round($totalCalculado, 2);
                $valor = isset($data['valor']) ? (float) $data['valor'] : $totalCalculado;

                if (isset($data['valor']) && abs($valor - $totalCalculado) > 0.02) {
                    $avisos[] = sprintf(
                        'o total indicado (%.2f) nao bate com a soma das linhas com IVA (%.2f); foi guardado o total indicado.',
                        $valor,
                        $totalCalculado
                    );
                }

                $categoria = $data['categoria'] ?? 'outro';

                $despesa = Despesa::query()->create([
                    'titulo' => $data['titulo'] ?? $this->tituloPorOmissao($data),
                    'numero_fatura' => $data['numero_fatura'] ?? null,
                    'fornecedor' => $data['fornecedor'] ?? null,
                    'valor' => $valor,
                    'data' => $data['data'],
                    'campanha_id' => $campanha?->id,
                    'categoria' => $categoria,
                    'notas' => $data['notas'] ?? null,
                ]);

                foreach ($linhas as $linha) {
                    $despesa->items()->create($linha);
                }

                $despesa->load('items.produto');

                $movimentos = [];

                if ($data['dar_entrada_em_stock'] ?? true) {
                    $movimentos = $this->stock->processarEntradas($despesa);

                    if ($movimentos === []) {
                        $avisos[] = 'nenhuma linha ficou ligada a um produto; nao houve entrada em stock.';
                    }
                }

                $custo = null;

                if (($data['criar_custo'] ?? true) && $valor > 0) {
                    $custo = Custo::query()->create([
                        'descricao' => $this->stock->referencia($despesa),
                        'tipo' => self::CATEGORIA_PARA_TIPO_CUSTO[$categoria] ?? 'outro',
                        'valor' => $valor,
                        'data_custo' => $data['data'],
                        'campanha_id' => $campanha?->id,
                        'maquina_id' => $maquina?->id,
                        'referencia_externa' => 'fatura-'.$despesa->id,
                    ]);
                }

                return [$despesa, $custo, $movimentos, $avisos];
            });
        } catch (ValidationException $exception) {
            return $this->erro422($exception->errors());
        }

        return $this->criado(
            $this->formatar($despesa, $custo, $movimentos),
            array_merge($avisos, $avisosCriacao)
        );
    }

    private function resolverProduto(array $linha, bool $criarProdutos, int $indice, array &$avisos): ?Produto
    {
        // O codigo da fatura e a referencia mais fiavel para reencontrar o
        // produto na proxima fatura do mesmo fornecedor.
        $referencia = $this->valorReferencia($linha['produto'] ?? null)
            ?? $linha['codigo']
            ?? $linha['numero_autorizacao_dgav']
            ?? null;

        if ($referencia === null || $referencia === '') {
            $avisos[] = "linha {$indice} sem produto identificado; fica registada na fatura mas sem ligacao ao catalogo nem stock.";

            return null;
        }

        try {
            return $this->resolvedor->resolverProduto($referencia);
        } catch (ValidationException $exception) {
            $existeAlgum = Produto::query()
                ->where('nome', $referencia)
                ->orWhere('numero_autorizacao_dgav', $referencia)
                ->exists();

            // So criamos quando nao existe mesmo nada. Se existir, o erro e de
            // ambiguidade (varios candidatos) e criar outro so pioraria.
            if (! $criarProdutos || $existeAlgum) {
                throw $exception;
            }
        }

        $tipo = Produto::normalizarTipo($linha['tipo_produto'] ?? null) ?? 'outro';
        $dgav = $linha['numero_autorizacao_dgav'] ?? null;

        if ($tipo === Produto::TIPO_FITOFARMACO && blank($dgav)) {
            throw ValidationException::withMessages([
                "linhas.{$indice}.numero_autorizacao_dgav" => [
                    'Produto fitofarmaceutico novo precisa de numero_autorizacao_dgav para ser criado (conformidade DGAV).',
                ],
            ]);
        }

        // Se a referencia usada foi o codigo da fatura, o nome do produto deve
        // ser a descricao e nao o codigo.
        $nome = (! empty($linha['codigo']) && (string) $referencia === (string) $linha['codigo'])
            ? ($linha['descricao'] ?? (string) $referencia)
            : (string) $referencia;

        $produto = Produto::query()->create([
            'nome' => $nome,
            'tipo' => $tipo,
            'numero_autorizacao_dgav' => $dgav,
            'codigo_interno' => $linha['codigo'] ?? null,
            'unidade_medida' => $linha['unidade_medida'] ?? 'un',
            'custo_unitario' => (float) $linha['preco_unitario'],
            'estabelecimento_venda_nome' => $linha['estabelecimento_venda_nome'] ?? null,
            'estabelecimento_venda_autorizacao' => $linha['estabelecimento_venda_autorizacao'] ?? null,
        ]);

        $avisos[] = "produto criado: {$produto->nome} (tipo {$tipo}".($produto->codigo_interno ? ", codigo {$produto->codigo_interno}" : '').").";

        return $produto;
    }

    private function tituloPorOmissao(array $data): string
    {
        $partes = array_filter([
            $data['fornecedor'] ?? null,
            $data['numero_fatura'] ?? null,
        ]);

        return $partes === [] ? 'Fatura de compra' : 'Fatura '.implode(' ', $partes);
    }

    private function formatar(Despesa $despesa, ?Custo $custo, array $movimentos = []): array
    {
        return [
            'despesa' => [
                'id' => $despesa->id,
                'titulo' => $despesa->titulo,
                'numero_fatura' => $despesa->numero_fatura,
                'fornecedor' => $despesa->fornecedor,
                'categoria' => $despesa->categoria,
                'valor' => $despesa->valor,
                'data' => $despesa->data?->toDateString(),
                'linhas' => $despesa->items->map(fn ($item) => [
                    'id' => $item->id,
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                    'iva_percentagem' => $item->iva_percentagem,
                    'produto' => $item->produto ? [
                        'id' => $item->produto->id,
                        'nome' => $item->produto->nome,
                        'numero_autorizacao_dgav' => $item->produto->numero_autorizacao_dgav,
                    ] : null,
                ])->values()->all(),
            ],
            'movimentos_stock' => $movimentos,
            'custo' => $custo === null ? null : [
                'id' => $custo->id,
                'tipo' => $custo->tipo,
                'valor' => $custo->valor,
                'data' => $custo->data_custo?->toDateString(),
            ],
        ];
    }

    private function valorReferencia(mixed $referencia): int|string|null
    {
        if ($referencia === null) {
            return null;
        }

        if (! is_array($referencia)) {
            return $referencia;
        }

        foreach (['id', 'numero_autorizacao_dgav', 'nome'] as $chave) {
            if (array_key_exists($chave, $referencia) && $referencia[$chave] !== null && $referencia[$chave] !== '') {
                return $referencia[$chave];
            }
        }

        return null;
    }
}
