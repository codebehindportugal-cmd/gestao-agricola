<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCulturaRequest;
use App\Http\Requests\UpdateCulturaRequest;
use App\Models\Cultura;
use App\Models\Parcela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CulturaManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cultura::class);

        $filters = $request->only(['search', 'estado', 'parcela_id', 'tipo', 'grupo_cultura']);

        $culturas = Cultura::query()
            ->with('parcela.terreno:id,nome')
            ->withCount(['operacoes', 'colheitas'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('grupo_cultura', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('variedade', 'like', "%{$search}%");
                });
            })
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filters['parcela_id'] ?? null, fn ($query, $parcelaId) => $query->where('parcela_id', $parcelaId))
            ->when($filters['grupo_cultura'] ?? null, fn ($query, $grupo) => $query->where('grupo_cultura', $grupo))
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', 'like', "%{$tipo}%"))
            ->orderByDesc('data_plantacao')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Cultura $cultura) => [
                'id' => $cultura->id,
                'parcela_id' => $cultura->parcela_id,
                'parcela_nome' => $cultura->parcela?->nome,
                'terreno_nome' => $cultura->parcela?->terreno?->nome,
                'nome' => $cultura->nome,
                'grupo_cultura' => $cultura->grupo_cultura,
                'ciclo_produtivo' => $cultura->ciclo_produtivo,
                'ano_inicio_producao' => $cultura->ano_inicio_producao,
                'data_fim_producao' => optional($cultura->data_fim_producao)?->format('Y-m-d'),
                'tipo' => $cultura->tipo,
                'variedade' => $cultura->variedade,
                'data_plantacao' => optional($cultura->data_plantacao)?->format('Y-m-d'),
                'previsao_colheita' => optional($cultura->previsao_colheita)?->format('Y-m-d'),
                'ciclo_dias' => $cultura->ciclo_dias,
                'quantidade_esperada' => $cultura->quantidade_esperada,
                'unidade_medida' => $cultura->unidade_medida,
                'estado' => $cultura->estado,
                'observacoes' => $cultura->observacoes,
                'operacoes_count' => $cultura->operacoes_count,
                'colheitas_count' => $cultura->colheitas_count,
                'updated_at' => optional($cultura->updated_at)?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Culturas/Index', [
            'culturas' => $culturas,
            'filters' => $filters,
            'summary' => [
                'total' => Cultura::query()->count(),
                'ativas' => Cultura::query()->whereIn('estado', ['planejada', 'em_crescimento', 'madura'])->count(),
                'permanentes' => Cultura::query()->where('ciclo_produtivo', 'permanente')->count(),
                'colhidas' => Cultura::query()->where('estado', 'colhida')->count(),
                'quantidade_esperada' => (float) Cultura::query()->sum('quantidade_esperada'),
            ],
            'can' => [
                'create' => $request->user()->can('create', Cultura::class),
                'delete' => $request->user()->hasRole('admin'),
            ],
            'estadoOptions' => ['planejada', 'em_crescimento', 'madura', 'colhida', 'cancelada'],
            'cicloOptions' => [
                ['value' => 'anual', 'label' => 'Anual'],
                ['value' => 'permanente', 'label' => 'Permanente'],
            ],
            'grupoOptions' => $this->grupoOptions(),
            'tipoOptions' => $this->tipoOptions(),
            'variedadeOptions' => $this->variedadeOptions(),
            'parcelas' => Parcela::query()
                ->with('terreno:id,nome')
                ->orderBy('nome')
                ->get(['id', 'terreno_id', 'nome'])
                ->map(fn (Parcela $parcela) => [
                    'id' => $parcela->id,
                    'nome' => $parcela->nome,
                    'terreno_nome' => $parcela->terreno?->nome,
                ]),
        ]);
    }

    public function store(StoreCulturaRequest $request): RedirectResponse
    {
        $this->authorize('create', Cultura::class);

        try {
            Cultura::query()->create($request->validated());
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a cultura. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.culturas.index', $this->redirectFilters($request))
            ->with('success', 'Cultura criada com sucesso.');
    }

    public function update(UpdateCulturaRequest $request, Cultura $cultura): RedirectResponse
    {
        $this->authorize('update', $cultura);

        try {
            $cultura->update($request->validated());
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a cultura. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.culturas.index', $this->redirectFilters($request))
            ->with('success', 'Cultura atualizada com sucesso.');
    }

    public function destroy(Request $request, Cultura $cultura): RedirectResponse
    {
        $this->authorize('delete', $cultura);

        try {
            $cultura->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a cultura. Confirme se não existem operações ou colheitas associadas.', $exception);
        }

        return redirect()
            ->route('app.culturas.index', $this->redirectFilters($request))
            ->with('success', 'Cultura removida com sucesso.');
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'estado', 'parcela_id', 'tipo', 'grupo_cultura']));
    }

    private function grupoOptions(): array
    {
        return [
            ['value' => 'arvores_fruto', 'label' => 'Árvores de fruto'],
            ['value' => 'olival', 'label' => 'Olival'],
            ['value' => 'vinha', 'label' => 'Vinha'],
            ['value' => 'horticolas', 'label' => 'Hortícolas'],
            ['value' => 'cereais', 'label' => 'Cereais'],
            ['value' => 'leguminosas', 'label' => 'Leguminosas'],
            ['value' => 'forragens', 'label' => 'Forragens'],
            ['value' => 'aromaticas', 'label' => 'Aromáticas e medicinais'],
            ['value' => 'florestais', 'label' => 'Florestais'],
            ['value' => 'ornamentais', 'label' => 'Ornamentais'],
            ['value' => 'outro', 'label' => 'Outro'],
        ];
    }

    private function tipoOptions(): array
    {
        $base = [
            'Abacateiro', 'Amendoeira', 'Aveia', 'Batata', 'Cebola', 'Centeio', 'Citrinos',
            'Ervilha', 'Faveira', 'Figueira', 'Macieira', 'Milho', 'Nogueira', 'Oliveira',
            'Pereira', 'Pessegueiro', 'Tomate', 'Trigo', 'Videira',
        ];

        $existing = Cultura::query()
            ->whereNotNull('tipo')
            ->distinct()
            ->pluck('tipo')
            ->filter()
            ->all();

        return collect([...$base, ...$existing])->unique()->sort()->values()->all();
    }

    private function variedadeOptions(): array
    {
        $base = [
            'Arbequina', 'Arbosana', 'Galega', 'Cobrançosa', 'Touriga Nacional', 'Aragonez',
            'Fuji', 'Golden', 'Rocha', 'Laranja Valencia', 'Hayward',
        ];

        $existing = Cultura::query()
            ->whereNotNull('variedade')
            ->distinct()
            ->pluck('variedade')
            ->filter()
            ->all();

        return collect([...$base, ...$existing])->unique()->sort()->values()->all();
    }
}
