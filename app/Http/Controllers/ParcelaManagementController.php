<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelaRequest;
use App\Http\Requests\UpdateParcelaRequest;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParcelaManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Parcela::class);

        $filters = $request->only(['search', 'estado', 'terreno_id']);

        $parcelas = Parcela::query()
            ->with('terreno:id,nome')
            ->withCount(['operacoes'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('numero_parcela', 'like', "%{$search}%")
                        ->orWhere('descricao', 'like', "%{$search}%");
                });
            })
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filters['terreno_id'] ?? null, fn ($query, $terrenoId) => $query->where('terreno_id', $terrenoId))
            ->orderBy('nome')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Parcela $parcela) => [
                'id' => $parcela->id,
                'terreno_id' => $parcela->terreno_id,
                'terreno_nome' => $parcela->terreno?->nome,
                'nome' => $parcela->nome,
                'numero_parcela' => $parcela->numero_parcela,
                'area_total' => $parcela->area_total,
                'area_util' => $parcela->area_util,
                'descricao' => $parcela->descricao,
                'estado' => $parcela->estado,
                'tipo_ocupacao' => $parcela->tipo_ocupacao,
                'numero_arvores' => $parcela->numero_arvores,
                'compasso_linha_m' => $parcela->compasso_linha_m,
                'compasso_planta_m' => $parcela->compasso_planta_m,
                'latitude' => $parcela->latitude,
                'longitude' => $parcela->longitude,
                'poligono' => $parcela->poligono,
                'operacoes_count' => $parcela->operacoes_count,
                'updated_at' => optional($parcela->updated_at)?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Parcelas/Index', [
            'parcelas' => $parcelas,
            'filters' => $filters,
            'summary' => [
                'total' => Parcela::query()->count(),
                'cultivadas' => Parcela::query()->where('estado', 'cultivada')->count(),
                'pomares' => Parcela::query()->where('tipo_ocupacao', 'pomar')->count(),
                'arvores' => (int) Parcela::query()->sum('numero_arvores'),
                'area_total' => (float) Parcela::query()->sum('area_total'),
                'area_util' => (float) Parcela::query()->sum('area_util'),
            ],
            'can' => [
                'create' => $request->user()->can('create', Parcela::class),
                'delete' => $request->user()->can('delete', new Parcela()),
            ],
            'estadoOptions' => $this->estadoOptions(),
            'terrenos' => $this->terrenosForSelect(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Parcela::class);

        return Inertia::render('Parcelas/Form', [
            'mode' => 'create',
            'parcela' => null,
            'filters' => $request->only(['search', 'estado', 'terreno_id']),
            'estadoOptions' => $this->estadoOptions(),
            'terrenos' => $this->terrenosForSelect(),
        ]);
    }

    public function edit(Request $request, Parcela $parcela): Response
    {
        $this->authorize('update', $parcela);

        return Inertia::render('Parcelas/Form', [
            'mode' => 'edit',
            'parcela' => $this->serializeParcela($parcela),
            'filters' => $request->only(['search', 'estado', 'terreno_id']),
            'estadoOptions' => $this->estadoOptions(),
            'terrenos' => $this->terrenosForSelect(),
        ]);
    }

    public function store(StoreParcelaRequest $request): RedirectResponse
    {
        $this->authorize('create', Parcela::class);

        try {
            Parcela::query()->create($this->normalizePayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a parcela. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.parcelas.index', $this->redirectFilters($request))
            ->with('success', 'Parcela criada com sucesso.');
    }

    public function update(UpdateParcelaRequest $request, Parcela $parcela): RedirectResponse
    {
        $this->authorize('update', $parcela);

        try {
            $parcela->update($this->normalizePayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a parcela. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.parcelas.index', $this->redirectFilters($request))
            ->with('success', 'Parcela atualizada com sucesso.');
    }

    public function destroy(Request $request, Parcela $parcela): RedirectResponse
    {
        $this->authorize('delete', $parcela);

        try {
            $parcela->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a parcela. Confirme se não existem culturas ou operações associadas.', $exception);
        }

        return redirect()
            ->route('app.parcelas.index', $this->redirectFilters($request))
            ->with('success', 'Parcela removida com sucesso.');
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'estado', 'terreno_id']));
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->validated();

        $data['tipo_ocupacao'] = $data['tipo_ocupacao'] ?? 'culturas_anuais';

        if (array_key_exists('poligono', $data) && empty($data['poligono'])) {
            $data['poligono'] = null;
        }

        return $data;
    }

    private function serializeParcela(Parcela $parcela): array
    {
        return [
            'id' => $parcela->id,
            'terreno_id' => $parcela->terreno_id,
            'nome' => $parcela->nome,
            'numero_parcela' => $parcela->numero_parcela,
            'area_total' => $parcela->area_total,
            'area_util' => $parcela->area_util,
            'descricao' => $parcela->descricao,
            'estado' => $parcela->estado,
            'tipo_ocupacao' => $parcela->tipo_ocupacao,
            'numero_arvores' => $parcela->numero_arvores,
            'compasso_linha_m' => $parcela->compasso_linha_m,
            'compasso_planta_m' => $parcela->compasso_planta_m,
            'latitude' => $parcela->latitude,
            'longitude' => $parcela->longitude,
            'poligono' => $parcela->poligono,
        ];
    }

    private function terrenosForSelect()
    {
        return Terreno::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'area_total', 'latitude', 'longitude', 'poligono'])
            ->map(fn (Terreno $terreno) => [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
                'area_total' => $terreno->area_total,
                'latitude' => $terreno->latitude,
                'longitude' => $terreno->longitude,
                'poligono' => $terreno->poligono,
            ]);
    }

    private function estadoOptions(): array
    {
        return ['livre', 'cultivada', 'em_preparacao', 'pousio'];
    }
}
