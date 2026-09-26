<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Manutencao;
use App\Models\MovimentoStock;
use App\Models\Receita;
use App\Models\Stock;
use App\Services\ResolvedorReferencias;
use App\Services\ResumoPorEspecieService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Leitura das contas da exploracao.
 *
 * A API sabia gravar mas quase nao sabia contar: so havia /tesouraria. Quem
 * registava pelo chat tinha de ir ao ecra para responder a "quanto custou a
 * apanha das pereiras" ou "esta fatura ja entrou". Estes endpoints sao esses
 * olhos — todos GET, todos com os mesmos filtros de periodo (`de`, `ate`) e a
 * mesma paginacao (`pagina`, `por_pagina`, ate 100).
 *
 * Referencias (campanha, parcela, cultura, maquina, alfaia, produto) aceitam
 * id ou nome, como no resto da API.
 */
class ConsultaController extends Controller
{
    use RespondeJson;

    public function __construct(
        private readonly ResolvedorReferencias $resolvedor,
        private readonly ResumoPorEspecieService $resumoEspecies
    ) {
    }

    /**
     * A campanha em numeros, repartida por especie.
     *
     * GET /api/v1/campanhas/{referencia}/resumo  (id ou nome)
     * GET /api/v1/resumo                          (a campanha em curso)
     */
    public function resumo(Request $request, ?string $referencia = null): JsonResponse
    {
        try {
            $campanha = $referencia === null
                ? $this->campanhaEmCurso()
                : $this->resolvedor->resolverCampanha($referencia);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        if ($campanha === null) {
            return $this->erro422(['campanha' => ['Nao ha nenhuma campanha registada.']]);
        }

        $resumo = $this->resumoEspecies->paraCampanha($campanha);

        return $this->ok([
            ...$resumo,
            'campanha' => [
                ...$resumo['campanha'],
                'status' => $campanha->status,
                'area_ha' => $campanha->area_total_ha,
                'custo_total' => $campanha->custo_total_calculado,
                'receita_total' => $campanha->receita_total,
                'margem' => $campanha->margem,
                'producao_kg' => $campanha->producao_total_kg,
                'custo_kg' => $campanha->custo_por_kg,
                'preco_medio_venda' => $campanha->preco_medio_venda,
            ],
        ]);
    }

    /** GET /api/v1/custos — o que se gastou, e em quê. */
    public function custos(Request $request): JsonResponse
    {
        $query = Custo::query()->with(['campanha:id,nome,ano', 'cultura:id,nome', 'parcela:id,nome', 'maquina:id,nome', 'alfaia:id,nome']);

        $this->filtrarPorReferencia($query, $request, 'campanha', 'campanha_id', 'resolverCampanha');
        $this->filtrarPorReferencia($query, $request, 'cultura', 'cultura_id', 'resolverCultura');
        $this->filtrarPorReferencia($query, $request, 'parcela', 'parcela_id', 'resolverParcela');
        $this->filtrarPorReferencia($query, $request, 'maquina', 'maquina_id', 'resolverMaquina');
        $this->filtrarPorReferencia($query, $request, 'alfaia', 'alfaia_id', 'resolverAlfaia');

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($request->has('rateavel')) {
            $query->where('rateavel', $request->boolean('rateavel'));
        }

        if ($request->boolean('sem_campanha')) {
            $query->whereNull('campanha_id');
        }

        $this->entreDatas($query, $request, 'data_custo');

        return $this->paginado(
            $query->orderByDesc('data_custo')->orderByDesc('id'),
            $request,
            fn (Custo $custo) => [
                'id' => $custo->id,
                'descricao' => $custo->descricao,
                'tipo' => $custo->tipo,
                'valor' => (float) $custo->valor,
                'data' => $custo->data_custo?->toDateString(),
                'campanha' => $custo->campanha?->nome_completo,
                'cultura' => $custo->cultura?->nome,
                'parcela' => $custo->parcela?->nome,
                'maquina' => $custo->maquina?->nome,
                'alfaia' => $custo->alfaia?->nome,
                'operacao_id' => $custo->operacao_id,
                'rateavel' => (bool) $custo->rateavel,
                'base_rateio' => $custo->base_rateio,
                'referencia_externa' => $custo->referencia_externa,
            ],
            ['total_valor' => $this->soma($query, 'valor')]
        );
    }

    /** GET /api/v1/colheitas — quilos colhidos. */
    public function colheitas(Request $request): JsonResponse
    {
        $query = Colheita::query()->with(['campanha:id,nome,ano', 'cultura:id,nome,tipo', 'parcela:id,nome,terreno_id', 'parcela.terreno:id,nome']);

        $this->filtrarPorReferencia($query, $request, 'campanha', 'campanha_id', 'resolverCampanha');
        $this->filtrarPorReferencia($query, $request, 'cultura', 'cultura_id', 'resolverCultura');
        $this->filtrarPorReferencia($query, $request, 'parcela', 'parcela_id', 'resolverParcela');
        $this->entreDatas($query, $request, 'data_colheita');

        return $this->paginado(
            $query->orderByDesc('data_colheita')->orderByDesc('id'),
            $request,
            fn (Colheita $colheita) => [
                'id' => $colheita->id,
                'data' => $colheita->data_colheita?->toDateString(),
                'quantidade' => (float) $colheita->quantidade_total,
                'unidade' => $colheita->unidade_medida,
                'perdas' => $colheita->quantidade_perdas === null ? null : (float) $colheita->quantidade_perdas,
                'qualidade' => $colheita->qualidade,
                'campanha' => $colheita->campanha?->nome_completo,
                'cultura' => $colheita->cultura?->nome,
                'especie' => $colheita->cultura?->tipo,
                'parcela' => $colheita->parcela?->nome,
                'terreno' => $colheita->parcela?->terreno?->nome,
                'operacao_id' => $colheita->operacao_id,
                'referencia_externa' => $colheita->referencia_externa,
            ],
            ['total_kg' => $this->soma($query, 'quantidade_total')]
        );
    }

    /** GET /api/v1/receitas — vendas e subsidios. */
    public function receitas(Request $request): JsonResponse
    {
        $query = Receita::query()->with(['campanha:id,nome,ano', 'cultura:id,nome']);

        $this->filtrarPorReferencia($query, $request, 'campanha', 'campanha_id', 'resolverCampanha');
        $this->filtrarPorReferencia($query, $request, 'cultura', 'cultura_id', 'resolverCultura');

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($comprador = $request->query('comprador')) {
            $query->where('comprador_nome', 'like', '%'.$comprador.'%');
        }

        $this->entreDatas($query, $request, 'data');

        return $this->paginado(
            $query->orderByDesc('data')->orderByDesc('id'),
            $request,
            fn (Receita $receita) => [
                'id' => $receita->id,
                'descricao' => $receita->descricao,
                'tipo' => $receita->tipo,
                'valor' => (float) $receita->valor,
                'quantidade' => $receita->quantidade === null ? null : (float) $receita->quantidade,
                'unidade' => $receita->unidade,
                'preco_unitario' => $receita->preco_efetivo,
                'data' => $receita->data?->toDateString(),
                'comprador' => $receita->comprador_nome,
                'documento' => $receita->documento,
                'campanha' => $receita->campanha?->nome_completo,
                'cultura' => $receita->cultura?->nome,
                'referencia_externa' => $receita->referencia_externa,
            ],
            [
                'total_valor' => $this->soma($query, 'valor'),
                'total_quantidade' => $this->soma($query, 'quantidade'),
            ]
        );
    }

    /**
     * GET /api/v1/despesas — faturas de compra.
     *
     * Serve sobretudo para responder a "esta fatura ja entrou?": procurar por
     * numero ou fornecedor antes de a voltar a enviar.
     */
    public function despesas(Request $request): JsonResponse
    {
        $query = Despesa::query()->with(['campanha:id,nome,ano', 'maquina:id,nome', 'alfaia:id,nome', 'items:id,despesa_id,descricao,quantidade,preco_unitario,desconto_percentagem,iva_percentagem,produto_id']);

        $this->filtrarPorReferencia($query, $request, 'campanha', 'campanha_id', 'resolverCampanha');
        $this->filtrarPorReferencia($query, $request, 'maquina', 'maquina_id', 'resolverMaquina');
        $this->filtrarPorReferencia($query, $request, 'alfaia', 'alfaia_id', 'resolverAlfaia');

        if ($numero = $request->query('numero')) {
            $query->where('numero_fatura', 'like', '%'.$numero.'%');
        }

        if ($fornecedor = $request->query('fornecedor')) {
            $query->where('fornecedor', 'like', '%'.$fornecedor.'%');
        }

        if ($categoria = $request->query('categoria')) {
            $query->where('categoria', $categoria);
        }

        $this->entreDatas($query, $request, 'data');

        return $this->paginado(
            $query->orderByDesc('data')->orderByDesc('id'),
            $request,
            fn (Despesa $despesa) => [
                'id' => $despesa->id,
                'titulo' => $despesa->titulo,
                'numero_fatura' => $despesa->numero_fatura,
                'fornecedor' => $despesa->fornecedor,
                'categoria' => $despesa->categoria,
                'valor' => (float) $despesa->valor,
                'data' => $despesa->data?->toDateString(),
                'campanha' => $despesa->campanha?->nome_completo,
                'equipamento' => $despesa->equipamento_nome,
                'tem_documento' => $despesa->ficheiro_path !== null,
                'linhas' => $despesa->items->count(),
            ],
            ['total_valor' => $this->soma($query, 'valor')]
        );
    }

    /** GET /api/v1/stock — o que ha em armazem, e os ultimos movimentos. */
    public function stock(Request $request): JsonResponse
    {
        $query = Stock::query()->with('produto:id,nome,tipo,unidade_medida,conteudo,custo_unitario');

        $this->filtrarPorReferencia($query, $request, 'produto', 'produto_id', 'resolverProduto');

        if ($request->boolean('so_com_stock')) {
            $query->where('quantidade', '>', 0);
        }

        if ($tipo = $request->query('tipo_produto')) {
            $query->whereHas('produto', fn (Builder $sub) => $sub->where('tipo', $tipo));
        }

        return $this->paginado(
            $query->orderBy('produto_id'),
            $request,
            fn (Stock $stock) => [
                'produto_id' => $stock->produto_id,
                'produto' => $stock->produto?->nome,
                'tipo' => $stock->produto?->tipo,
                'quantidade' => (float) $stock->quantidade,
                'unidade' => $stock->unidade_medida,
                'custo_unitario' => $stock->produto?->custo_unitario === null ? null : (float) $stock->produto->custo_unitario,
                'valor' => round((float) $stock->quantidade * (float) ($stock->produto?->custo_unitario ?? 0), 2),
                'atualizado' => $stock->data_atualizado?->toDateString(),
            ]
        );
    }

    /** GET /api/v1/movimentos-stock — entradas e saidas. */
    public function movimentosStock(Request $request): JsonResponse
    {
        $query = MovimentoStock::query()->with('produto:id,nome,unidade_medida');

        $this->filtrarPorReferencia($query, $request, 'produto', 'produto_id', 'resolverProduto');

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        $this->entreDatas($query, $request, 'created_at');

        return $this->paginado(
            $query->orderByDesc('id'),
            $request,
            fn (MovimentoStock $movimento) => [
                'id' => $movimento->id,
                'produto' => $movimento->produto?->nome,
                'tipo' => $movimento->tipo,
                'quantidade' => (float) $movimento->quantidade,
                'unidade' => $movimento->unidade_medida,
                'custo_unitario' => $movimento->custo_unitario === null ? null : (float) $movimento->custo_unitario,
                'referencia' => $movimento->referencia,
                'despesa_id' => $movimento->despesa_id,
                'data' => $movimento->created_at?->toDateString(),
                'notas' => $movimento->notas,
            ]
        );
    }

    /** GET /api/v1/manutencoes — revisoes de maquinas e alfaias. */
    public function manutencoes(Request $request): JsonResponse
    {
        $query = Manutencao::query()->with(['maquina:id,nome,tipo', 'alfaia:id,nome,tipo']);

        $this->filtrarPorReferencia($query, $request, 'maquina', 'maquina_id', 'resolverMaquina');
        $this->filtrarPorReferencia($query, $request, 'alfaia', 'alfaia_id', 'resolverAlfaia');

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        $this->entreDatas($query, $request, 'data_manutencao');

        return $this->paginado(
            $query->orderByDesc('data_manutencao')->orderByDesc('id'),
            $request,
            fn (Manutencao $manutencao) => [
                'id' => $manutencao->id,
                'data' => $manutencao->data_manutencao?->toDateString(),
                'tipo' => $manutencao->tipo,
                'descricao' => $manutencao->descricao,
                'custo' => $manutencao->custo === null ? null : (float) $manutencao->custo,
                'duracao_minutos' => $manutencao->duracao_minutos,
                'proxima' => $manutencao->proxima_manutencao?->toDateString(),
                'maquina' => $manutencao->maquina?->nome,
                'alfaia' => $manutencao->alfaia?->nome,
                'equipamento' => $manutencao->equipamento_nome,
                'observacoes' => $manutencao->observacoes,
            ],
            ['total_custo' => $this->soma($query, 'custo')]
        );
    }

    // ── Apoio ────────────────────────────────────────────────────────────────

    /** A campanha que cobre hoje; a ultima, fora de epoca. */
    private function campanhaEmCurso(): ?Campanha
    {
        $hoje = now()->startOfDay();

        return Campanha::query()
            ->whereDate('data_inicio', '<=', $hoje)
            ->where(fn (Builder $query) => $query->whereNull('data_fim')->orWhereDate('data_fim', '>=', $hoje))
            ->orderByDesc('data_inicio')
            ->first()
            ?? Campanha::query()->orderByDesc('ano')->orderByDesc('id')->first();
    }

    /**
     * Filtro por referencia que aceita id ou nome.
     *
     * Um nome que nao existe da 422 com candidatos, em vez de devolver uma
     * lista vazia que se leria como "nao ha nada" — a diferenca entre "a
     * campanha nao teve custos" e "escreveste o nome errado".
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function filtrarPorReferencia(Builder $query, Request $request, string $campo, string $coluna, string $metodo): void
    {
        $valor = $request->query($campo);

        if ($valor === null || $valor === '') {
            return;
        }

        try {
            $modelo = $this->resolvedor->{$metodo}($valor);
        } catch (ValidationException $excepcao) {
            // Na forma da casa (sucesso/dados/avisos/erros) em vez da do
            // Laravel, para quem le a resposta nao ter de conhecer as duas.
            throw new HttpResponseException($this->erro422($excepcao->errors()));
        }

        $query->where($coluna, $modelo->id);
    }

    /** @param Builder<covariant \Illuminate\Database\Eloquent\Model> $query */
    private function entreDatas(Builder $query, Request $request, string $coluna): void
    {
        if ($de = $request->query('de')) {
            $query->whereDate($coluna, '>=', $de);
        }

        if ($ate = $request->query('ate')) {
            $query->whereDate($coluna, '<=', $ate);
        }
    }

    /**
     * Soma de uma coluna sobre os MESMOS filtros, antes da paginacao.
     *
     * Um clone: paginar depois de somar deixava o sum() a contar so a pagina.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function soma(Builder $query, string $coluna): float
    {
        return round((float) (clone $query)->sum($coluna), 2);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $extra
     */
    private function paginado(Builder $query, Request $request, callable $formatar, array $extra = []): JsonResponse
    {
        $porPagina = min(max((int) $request->query('por_pagina', 25), 1), 100);
        $pagina = $query->paginate($porPagina, ['*'], 'pagina');

        return $this->ok([
            ...$extra,
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'paginas' => $pagina->lastPage(),
            'por_pagina' => $pagina->perPage(),
            'linhas' => collect($pagina->items())->map($formatar)->values()->all(),
        ]);
    }
}
