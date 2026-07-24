<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelaRequest;
use App\Http\Requests\UpdateParcelaRequest;
use App\Models\Cultura;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParcelaManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Parcela::class);

        $filters = $request->only(['search', 'estado', 'terreno_id']);

        $parcelas = Parcela::query()
            ->with(['terreno:id,nome', 'culturas:id,parcela_id,nome,variedade,tipo,estado,data_plantacao'])
            ->withCount(['operacoes', 'culturas'])
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
                'culturas_count' => $parcela->culturas_count,
                'culturas' => $parcela->culturas->map(fn (Cultura $cultura) => [
                    'id' => $cultura->id,
                    'nome' => $cultura->nome,
                    'variedade' => $cultura->variedade,
                    'tipo' => $cultura->tipo,
                    'estado' => $cultura->estado,
                    'data_plantacao' => optional($cultura->data_plantacao)?->format('Y-m-d'),
                    'label' => $this->culturaLabel($cultura),
                ])->values(),
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
            DB::transaction(function () use ($request) {
                $parcela = Parcela::query()->create($this->normalizePayload($request));
                $this->syncCulturaPrincipal($parcela, $request);
            });
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
            DB::transaction(function () use ($request, $parcela) {
                $parcela->update($this->normalizePayload($request));
                $this->syncCulturaPrincipal($parcela->fresh(), $request);
            });
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

        unset(
            $data['cultura_nome'],
            $data['cultura_variedade'],
            $data['cultura_tipo'],
            $data['cultura_data_plantacao'],
            $data['cultura_estado'],
        );

        return $data;
    }

    private function syncCulturaPrincipal(Parcela $parcela, Request $request): void
    {
        $nome = trim((string) $request->input('cultura_nome', ''));

        if ($nome === '') {
            return;
        }

        $cultura = $parcela->culturas()
            ->orderByRaw("CASE WHEN estado IN ('em_crescimento', 'madura', 'planejada') THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        $payload = [
            'nome' => $nome,
            'tipo' => $request->input('cultura_tipo') ?: $parcela->tipo_ocupacao ?: 'culturas_anuais',
            'variedade' => $request->input('cultura_variedade') ?: null,
            'data_plantacao' => $request->input('cultura_data_plantacao') ?: now()->toDateString(),
            'estado' => $request->input('cultura_estado') ?: 'em_crescimento',
        ];

        if ($cultura) {
            $cultura->update($payload);
            return;
        }

        $parcela->culturas()->create($payload);
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
            'culturas' => $parcela->culturas()
                ->orderBy('nome')
                ->get(['id', 'parcela_id', 'nome', 'variedade', 'tipo', 'estado', 'data_plantacao'])
                ->map(fn (Cultura $cultura) => [
                    'id' => $cultura->id,
                    'nome' => $cultura->nome,
                    'variedade' => $cultura->variedade,
                    'tipo' => $cultura->tipo,
                    'estado' => $cultura->estado,
                    'data_plantacao' => optional($cultura->data_plantacao)?->format('Y-m-d'),
                    'label' => $this->culturaLabel($cultura),
                ])->values(),
        ];
    }

    private function culturaLabel(Cultura $cultura): string
    {
        return trim($cultura->nome.($cultura->variedade ? " - {$cultura->variedade}" : ''));
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
