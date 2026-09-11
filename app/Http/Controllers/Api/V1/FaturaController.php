<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFaturaApiRequest;
use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Produto;
use App\Services\MovimentoStockService;
use App\Services\PaperInvoice\TamanhoEmbalagem;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
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

                return $this->criado($this->formatar($existente->load(['items.produto', 'campanha']), null), $avisos);
            }
        }

        try {
            [$despesa, $custo, $movimentos, $avisosCriacao] = DB::transaction(function () use ($data) {
                $avisos = [];

                $campanha = null;
                $maquina = null;

                if (! empty($data['campanha'])) {
                    $campanha = $this->resolvedor->resolverCampanha($this->valorReferencia($data['campanha']));
                } else {
                    $campanha = $this->campanhaPelaData($data['data'], $avisos);
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

                    // O tamanho da embalagem que a propria fatura declara: o
                    // campo do pedido, ou o que vem escrito na designacao.
                    $declarado = $this->conteudoDeclarado($linha);

                    $produto = $this->resolverProduto($linha, $criarProdutos, $indice, $avisos, $declarado);
                    $embalagem = $this->conteudoEfetivo($declarado, $produto, $indice, $avisos);

                    if ($produto !== null) {
                        $actualizacoes = [];

                        if ($actualizarCusto) {
                            // O custo do catalogo e' por unidade de stock: o
                            // preco pago (ja com desconto) a dividir pelo que
                            // leva a embalagem desta linha.
                            $precoUnitario = round(
                                $this->precoLiquido($linha) / $embalagem['conteudo'],
                                4
                            );

                            if ((float) $produto->custo_unitario !== $precoUnitario) {
                                $actualizacoes['custo_unitario'] = $precoUnitario;
                            }
                        }

                        // O codigo e o DGAV ficam gravados no produto para a
                        // fatura seguinte o apanhar logo, sem criar um duplicado.
                        if (! empty($linha['codigo']) && blank($produto->codigo_interno)) {
                            $actualizacoes['codigo_interno'] = $linha['codigo'];
                        }

                        if (! empty($linha['numero_autorizacao_dgav']) && blank($produto->numero_autorizacao_dgav)) {
                            $actualizacoes['numero_autorizacao_dgav'] = $linha['numero_autorizacao_dgav'];
                        }

                        if (blank($produto->unidade_medida) && filled($embalagem['unidade'])) {
                            $actualizacoes['unidade_medida'] = $embalagem['unidade'];
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
                    $desconto = (float) ($linha['desconto_percentagem'] ?? 0);
                    $iva = (float) ($linha['iva_percentagem'] ?? 0);
                    // O desconto entra antes do IVA, como na fatura.
                    $totalCalculado += $quantidade * $preco * (1 - $desconto / 100) * (1 + $iva / 100);

                    $linhas[] = [
                        'descricao' => $linha['descricao'],
                        'quantidade' => $quantidade,
                        'conteudo_embalagem' => $embalagem['conteudo'],
                        'unidade_embalagem' => $embalagem['unidade'],
                        'preco_unitario' => $preco,
                        'desconto_percentagem' => $desconto,
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

                $despesa->load(['items.produto', 'campanha']);

                $movimentos = [];

                if ($data['dar_entrada_em_stock'] ?? true) {
                    $movimentos = $this->stock->processarEntradas($despesa);

                    if ($movimentos === []) {
                        $avisos[] = 'nenhuma linha ficou ligada a um produto; nao houve entrada em stock.';
                    }
                }

                $custo = null;

                if (($data['criar_custo'] ?? true) && $valor > 0) {
                    // Sem campanha e sem maquina, o custo nao tem onde encostar:
                    // nasce rateavel para o RateioCustosService o repartir pelas
                    // campanhas do periodo, em vez de ficar fora de todas as contas.
                    $rateavel = $data['rateavel'] ?? ($campanha === null && $maquina === null);

                    $custo = Custo::query()->create([
                        'descricao' => $this->stock->referencia($despesa),
                        'tipo' => self::CATEGORIA_PARA_TIPO_CUSTO[$categoria] ?? 'outro',
                        'valor' => $valor,
                        'data_custo' => $data['data'],
                        'campanha_id' => $campanha?->id,
                        'maquina_id' => $maquina?->id,
                        'rateavel' => $rateavel && $campanha === null,
                        'base_rateio' => $rateavel && $campanha === null
                            ? ($data['base_rateio'] ?? 'kg')
                            : null,
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

    /**
     * Campanha da fatura quando o pedido nao a indica.
     *
     * Nao ha "a campanha activa": nesta exploracao correm varias ao mesmo
     * tempo (pereiras, macieiras, culturas anuais), todas com o mesmo periodo.
     * Por isso so se escolhe quando a data da fatura cai dentro de uma unica
     * campanha. Havendo mais do que uma, a fatura fica sem campanha e o custo
     * nasce rateavel, para ser repartido pelos quilos colhidos em vez de ser
     * atirado ao calhas para uma delas.
     */
    private function campanhaPelaData(string $data, array &$avisos): ?Campanha
    {
        $dia = Carbon::parse($data)->startOfDay();

        $candidatas = Campanha::query()
            ->whereDate('data_inicio', '<=', $dia)
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $dia))
            ->orderBy('id')
            ->get(['id', 'nome', 'ano', 'cultura_id']);

        if ($candidatas->count() === 1) {
            return $candidatas->first();
        }

        if ($candidatas->isEmpty()) {
            $avisos[] = 'nenhuma campanha cobre a data da fatura; o custo fica rateavel pelas campanhas do periodo.';

            return null;
        }

        $nomes = $candidatas->map(fn (Campanha $c) => $c->nome_completo)->implode(', ');

        $avisos[] = sprintf(
            'a data da fatura cai em %d campanhas (%s); a despesa fica sem campanha e o custo rateavel. '
            .'Indique "campanha" no pedido, ou atribua-a no ecra, se pertencer so a uma.',
            $candidatas->count(),
            $nomes
        );

        return null;
    }

    /**
     * Tamanho da embalagem que a fatura declara, sem olhar ao catalogo.
     *
     * O campo do pedido ganha a tudo — quem le a fatura ve o papel e o servidor
     * nao. A seguir vem o que esta escrito na designacao, que e onde os
     * fornecedores o escrevem sempre.
     *
     * @return array{conteudo: float, unidade: ?string}|null
     */
    private function conteudoDeclarado(array $linha): ?array
    {
        if (! empty($linha['conteudo_embalagem']) && (float) $linha['conteudo_embalagem'] > 0) {
            $unidade = $linha['unidade_embalagem'] ?? $linha['unidade_medida'] ?? null;

            $normalizado = TamanhoEmbalagem::paraUnidadeBase(
                (float) $linha['conteudo_embalagem'],
                (string) ($unidade ?? 'un')
            );

            return [
                'conteudo' => $normalizado['conteudo'],
                'unidade' => $unidade === null ? null : $normalizado['unidade'],
            ];
        }

        $daDescricao = TamanhoEmbalagem::daDescricao($linha['descricao'] ?? '');

        if ($daDescricao === null) {
            return null;
        }

        $normalizado = TamanhoEmbalagem::paraUnidadeBase($daDescricao['conteudo'], $daDescricao['unidade']);

        return [
            'conteudo' => $normalizado['conteudo'],
            'unidade' => $normalizado['unidade'],
        ];
    }

    /**
     * O tamanho que esta linha usa para o stock, e a reconciliacao com o catalogo.
     *
     * O que a fatura diz ganha ao que esta gravado: o mesmo produto vende-se em
     * 5 L e em 20 L, e era por confiar sempre no catalogo que o ERUNE entrava a
     * 1 L. Quando o produto esta sem tamanho (nulo, 0 ou 1, que era o valor por
     * omissao antes desta coluna existir), corrige-se em silencio. Quando tem
     * outro tamanho a serio, nao se mexe nele e avisa-se — pode ser a embalagem
     * grande que ele costuma comprar.
     *
     * @param  array{conteudo: float, unidade: ?string}|null  $declarado
     * @return array{conteudo: float, unidade: ?string}
     */
    private function conteudoEfetivo(?array $declarado, ?Produto $produto, int $indice, array &$avisos): array
    {
        $doProduto = $produto === null ? null : (float) ($produto->conteudo ?: 0);

        if ($declarado === null) {
            return [
                'conteudo' => $doProduto > 0 ? $doProduto : 1.0,
                'unidade' => $produto?->unidade_medida,
            ];
        }

        $unidade = $declarado['unidade'] ?? $produto?->unidade_medida;

        if ($produto === null || $this->mesmoConteudo($declarado['conteudo'], $doProduto)) {
            return ['conteudo' => $declarado['conteudo'], 'unidade' => $unidade];
        }

        if ($doProduto === null || $doProduto <= 1) {
            $produto->update(array_filter([
                'conteudo' => $declarado['conteudo'],
                'unidade_medida' => blank($produto->unidade_medida) ? $unidade : null,
            ], fn ($valor) => $valor !== null));

            $avisos[] = sprintf(
                'conteudo do produto #%d (%s) corrigido para %s, como diz a fatura.',
                $produto->id,
                $this->comUnidade($doProduto ?: 1.0, $produto->unidade_medida),
                $this->comUnidade($declarado['conteudo'], $unidade)
            );

            return ['conteudo' => $declarado['conteudo'], 'unidade' => $unidade];
        }

        $avisos[] = sprintf(
            'conteudo do produto #%d (%s) difere da fatura (%s); a linha %d entra em stock pelo da fatura '
            .'e o catalogo fica como esta.',
            $produto->id,
            $this->comUnidade($doProduto, $produto->unidade_medida),
            $this->comUnidade($declarado['conteudo'], $unidade),
            $indice
        );

        return ['conteudo' => $declarado['conteudo'], 'unidade' => $unidade];
    }

    private function mesmoConteudo(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) < 0.0001;
    }

    private function comUnidade(float $conteudo, ?string $unidade): string
    {
        $numero = rtrim(rtrim(number_format($conteudo, 4, '.', ''), '0'), '.');

        return trim($numero.' '.($unidade ?? ''));
    }

    /**
     * Encontra o produto do catalogo pelas referencias que a linha traz.
     *
     * Tenta-as todas, nao so a primeira: o codigo do artigo e o mais fiavel,
     * depois o DGAV (identidade legal de um fitofarmaceutico), depois o nome, e
     * por fim o nome a comecar pela primeira palavra da designacao — "BANJO"
     * encontra "BANJO fluziname - 5 LT". Era por tentar uma so que o Banjo
     * entrou duas vezes no catalogo.
     *
     * @param  array{conteudo: float, unidade: ?string}|null  $declarado
     */
    private function resolverProduto(
        array $linha,
        bool $criarProdutos,
        int $indice,
        array &$avisos,
        ?array $declarado = null
    ): ?Produto {
        $nomeDado = $this->valorReferencia($linha['produto'] ?? null);
        $codigo = $linha['codigo'] ?? null;
        $dgav = $linha['numero_autorizacao_dgav'] ?? null;

        if (blank($nomeDado) && blank($codigo) && blank($dgav)) {
            $avisos[] = "linha {$indice} sem produto identificado; fica registada na fatura mas sem ligacao ao catalogo nem stock.";

            return null;
        }

        $ambiguidade = null;
        $nomeJaTentado = false;

        // Um "produto" numerico e um apontador explicito para o id; vai
        // primeiro. Um codigo de artigo numerico nao e um id, por isso nunca
        // passa pelo resolvedor — "produto 7" apanharia o produto #7 do
        // catalogo, que nao tem nada a ver com o artigo 7 do fornecedor.
        if (is_numeric($nomeDado) || (is_array($linha['produto'] ?? null) && ! empty($linha['produto']['id']))) {
            $nomeJaTentado = true;

            try {
                return $this->resolvedor->resolverProduto($nomeDado);
            } catch (ValidationException $excepcao) {
                $ambiguidade = $this->ehAmbiguidade($excepcao) ? $excepcao : null;
            }
        }

        foreach ([['codigo_interno', $codigo], ['numero_autorizacao_dgav', $dgav]] as [$coluna, $valor]) {
            if (blank($valor)) {
                continue;
            }

            $encontrados = Produto::query()->where($coluna, (string) $valor)->limit(2)->get();

            if ($encontrados->count() === 1) {
                return $encontrados->first();
            }
        }

        if (filled($nomeDado) && ! $nomeJaTentado) {
            try {
                return $this->resolvedor->resolverProduto($nomeDado);
            } catch (ValidationException $excepcao) {
                $ambiguidade ??= $this->ehAmbiguidade($excepcao) ? $excepcao : null;
            }
        }

        $porNome = $this->produtoPeloNome($linha);

        if ($porNome !== null) {
            $avisos[] = sprintf(
                'linha %d ligada ao produto ja existente "%s" (#%d) pelo nome.',
                $indice,
                $porNome->nome,
                $porNome->id
            );

            return $porNome;
        }

        if ($ambiguidade !== null) {
            throw $ambiguidade;
        }

        if (! $criarProdutos) {
            throw ValidationException::withMessages([
                "linhas.{$indice}.produto" => [
                    'Produto nao encontrado no catalogo e criar_produtos esta desligado.',
                ],
            ]);
        }

        $tipo = Produto::normalizarTipo($linha['tipo_produto'] ?? null) ?? 'outro';

        if ($tipo === Produto::TIPO_FITOFARMACO && blank($dgav)) {
            throw ValidationException::withMessages([
                "linhas.{$indice}.numero_autorizacao_dgav" => [
                    'Produto fitofarmaceutico novo precisa de numero_autorizacao_dgav para ser criado (conformidade DGAV).',
                ],
            ]);
        }

        // O nome do produto e o da designacao; o codigo do artigo nao e nome.
        $nome = (filled($nomeDado) && (string) $nomeDado !== (string) $codigo)
            ? (string) $nomeDado
            : (string) ($linha['descricao'] ?? $nomeDado ?? $codigo ?? $dgav);

        $produto = Produto::query()->create([
            'nome' => $nome,
            'tipo' => $tipo,
            'numero_autorizacao_dgav' => $dgav,
            'codigo_interno' => $codigo,
            'unidade_medida' => $linha['unidade_medida'] ?? $declarado['unidade'] ?? 'un',
            'conteudo' => $declarado['conteudo'] ?? null,
            'custo_unitario' => round($this->precoLiquido($linha) / ($declarado['conteudo'] ?? 1), 4),
            'estabelecimento_venda_nome' => $linha['estabelecimento_venda_nome'] ?? null,
            'estabelecimento_venda_autorizacao' => $linha['estabelecimento_venda_autorizacao'] ?? null,
        ]);

        $avisos[] = "produto criado: {$produto->nome} (tipo {$tipo}".($produto->codigo_interno ? ", codigo {$produto->codigo_interno}" : '').").";

        return $produto;
    }

    /**
     * Ultimo recurso: o produto cujo nome e a designacao da fatura sao o mesmo
     * artigo escrito com mais ou menos detalhe.
     *
     * "BANJO fluziname" no catalogo e "BANJO fluziname - 5 LT (AV 1199)" na
     * fatura sao o mesmo produto — era por nao os ligar que o Banjo entrou duas
     * vezes. Exige-se que um dos nomes comece pelo outro, e um so candidato:
     * bastar a primeira palavra ligaria "Adubo Cálcio" ao "Adubo Foliar X".
     */
    private function produtoPeloNome(array $linha): ?Produto
    {
        $designacao = (string) ($this->valorReferencia($linha['produto'] ?? null) ?? $linha['descricao'] ?? '');
        $normalizada = $this->normalizarNome($designacao);

        if (! preg_match('/[\p{L}\p{N}]{4,}/u', $normalizada, $encontrado)) {
            return null;
        }

        $candidatos = Produto::query()
            ->where('nome', 'like', $encontrado[0].'%')
            ->limit(6)
            ->get()
            ->filter(function (Produto $produto) use ($normalizada) {
                $nome = $this->normalizarNome($produto->nome);

                return $nome !== '' && (str_starts_with($normalizada, $nome) || str_starts_with($nome, $normalizada));
            });

        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }

    private function normalizarNome(string $nome): string
    {
        $minusculas = mb_strtolower(trim($nome));

        return trim((string) preg_replace('/\s+/u', ' ', $minusculas));
    }

    private function ehAmbiguidade(ValidationException $excepcao): bool
    {
        foreach ($excepcao->errors() as $mensagens) {
            foreach ($mensagens as $mensagem) {
                if (is_string($mensagem) && str_contains($mensagem, 'ambigua')) {
                    return true;
                }
            }
        }

        return false;
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
                'campanha' => $despesa->campanha === null ? null : [
                    'id' => $despesa->campanha->id,
                    'nome' => $despesa->campanha->nome_completo,
                ],
                'linhas' => $despesa->items->map(fn ($item) => [
                    'id' => $item->id,
                    'descricao' => $item->descricao,
                    'quantidade' => $item->quantidade,
                    'conteudo_embalagem' => $item->conteudo_efetivo,
                    'unidade_embalagem' => $item->unidade_embalagem,
                    'quantidade_base' => $item->quantidade_base,
                    'preco_unitario' => $item->preco_unitario,
                    'desconto_percentagem' => $item->desconto_percentagem,
                    'preco_liquido' => $item->preco_liquido,
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
                'rateavel' => (bool) $custo->rateavel,
                'base_rateio' => $custo->base_rateio,
            ],
        ];
    }

    /** Preco de tabela menos o desconto da linha. */
    private function precoLiquido(array $linha): float
    {
        return round(
            (float) $linha['preco_unitario'] * (1 - (float) ($linha['desconto_percentagem'] ?? 0) / 100),
            4
        );
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
