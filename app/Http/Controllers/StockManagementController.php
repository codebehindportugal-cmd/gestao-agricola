<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'tipo', 'estado']);

        $produtos = Produto::query()
            ->withSum('stocks as stock_atual', 'quantidade')
            ->with(['stocks' => fn ($query) => $query->orderByDesc('data_atualizado')->orderByDesc('id')])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('codigo_interno', 'like', "%{$search}%");
                });
            })
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString()
            ->through(function (Produto $produto) {
                $stockAtual = (float) ($produto->stock_atual ?? 0);
                $stockMinimo = (float) ($produto->stock_minimo ?? 0);
                $ultimoMovimento = $produto->stocks->first();

                return [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'tipo' => $produto->tipo,
                    'codigo_interno' => $produto->codigo_interno,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                    'estabelecimento_venda_nome' => $produto->estabelecimento_venda_nome,
                    'estabelecimento_venda_autorizacao' => $produto->estabelecimento_venda_autorizacao,
                    'unidade_medida' => $produto->unidade_medida,
                    'custo_unitario' => $produto->custo_unitario,
                    'stock_minimo' => $stockMinimo,
                    'stock_atual' => $stockAtual,
                    'abaixo_minimo' => $stockAtual <= $stockMinimo,
                    'valor_stock' => $produto->custo_unitario === null
                        ? null
                        : round($stockAtual * (float) $produto->custo_unitario, 2),
                    'ultimo_movimento_em' => optional($ultimoMovimento?->data_atualizado)?->format('Y-m-d'),
                    'ultimo_movimento_obs' => $ultimoMovimento?->observacoes,
                ];
            });

        return Inertia::render('Stock/Index', [
            'produtos' => $produtos,
            'filters' => $filters,
            'summary' => [
                'total_produtos' => Produto::query()->count(),
                'abaixo_minimo' => Produto::query()
                    ->withSum('stocks as stock_atual', 'quantidade')
                    ->get(['id', 'stock_minimo'])
                    ->filter(fn (Produto $produto) => (float) ($produto->stock_atual ?? 0) <= (float) ($produto->stock_minimo ?? 0))
                    ->count(),
                'valor_total' => Produto::query()
                    ->withSum('stocks as stock_atual', 'quantidade')
                    ->get(['id', 'custo_unitario'])
                    ->sum(fn (Produto $produto) => ((float) ($produto->stock_atual ?? 0)) * ((float) ($produto->custo_unitario ?? 0))),
            ],
            'tipoOptions' => Produto::query()
                ->whereNotNull('tipo')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo')
                ->values(),
        ]);
    }

    public function storeProduto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255'],
            'unidade_medida' => ['required', 'string', 'max:50'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'quantidade_inicial' => ['nullable', 'numeric', 'min:0'],
            'codigo_interno' => ['nullable', 'string', 'max:255', 'unique:produtos,codigo_interno'],
            'numero_autorizacao_dgav' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_nome' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_autorizacao' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $produto = Produto::query()->create([
                'nome' => $data['nome'],
                'tipo' => $data['tipo'],
                'unidade_medida' => $data['unidade_medida'],
                'custo_unitario' => $data['custo_unitario'] === '' ? null : ($data['custo_unitario'] ?? null),
                'stock_minimo' => $data['stock_minimo'] === '' ? 0 : (int) round((float) ($data['stock_minimo'] ?? 0)),
                'codigo_interno' => $data['codigo_interno'] ?? null,
                'numero_autorizacao_dgav' => $data['numero_autorizacao_dgav'] ?? null,
                'estabelecimento_venda_nome' => $data['estabelecimento_venda_nome'] ?? null,
                'estabelecimento_venda_autorizacao' => $data['estabelecimento_venda_autorizacao'] ?? null,
                'descricao' => $data['descricao'] ?? null,
            ]);

            if ((float) ($data['quantidade_inicial'] ?? 0) > 0) {
                Stock::query()->create([
                    'produto_id' => $produto->id,
                    'armazem_id' => null,
                    'quantidade' => (float) $data['quantidade_inicial'],
                    'unidade_medida' => $data['unidade_medida'],
                    'data_atualizado' => now()->toDateString(),
                    'observacoes' => 'Stock inicial.',
                ]);
            }
        });

        return redirect()
            ->route('app.stock.index', $this->redirectFilters($request))
            ->with('success', 'Produto criado no stock com sucesso.');
    }

    public function update(Request $request, Produto $produto): RedirectResponse
    {
        $data = $request->validate([
            'ajuste_tipo' => ['required', 'in:definir_total,adicionar'],
            'quantidade' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'unidade_medida' => ['required', 'string', 'max:50'],
            'observacoes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($produto, $data) {
            $stock = Stock::query()->firstOrNew([
                'produto_id' => $produto->id,
                'armazem_id' => null,
            ]);

            $quantidadeAtual = $stock->exists ? (float) $stock->quantidade : 0;
            $quantidadeNova = $data['ajuste_tipo'] === 'adicionar'
                ? $quantidadeAtual + (float) $data['quantidade']
                : (float) $data['quantidade'];

            $stock->fill([
                'unidade_medida' => $data['unidade_medida'],
                'quantidade' => $quantidadeNova,
                'data_atualizado' => now()->toDateString(),
                'observacoes' => $data['observacoes'] ?? null,
            ]);
            $stock->save();

            $produto->update([
                'unidade_medida' => $data['unidade_medida'],
                'stock_minimo' => $data['stock_minimo'] === '' ? 0 : (int) round((float) ($data['stock_minimo'] ?? 0)),
                'custo_unitario' => $data['custo_unitario'] === '' ? null : ($data['custo_unitario'] ?? null),
            ]);
        });

        return redirect()
            ->route('app.stock.index', $this->redirectFilters($request))
            ->with('success', 'Stock atualizado com sucesso.');
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'tipo', 'estado']));
    }
}
