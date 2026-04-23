<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTerrenoRequest;
use App\Http\Requests\UpdateTerrenoRequest;
use App\Models\Terreno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TerrenoManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Terreno::class);

        $filters = $request->only(['search', 'estado']);

        $terrenos = Terreno::query()
            ->withCount('parcelas')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('localizacao', 'like', "%{$search}%")
                        ->orWhere('tipo_solo', 'like', "%{$search}%");
                });
            })
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->orderBy('nome')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Terreno $terreno) => [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
                'descricao' => $terreno->descricao,
                'area_total' => $terreno->area_total,
                'tipo_solo' => $terreno->tipo_solo,
                'localizacao' => $terreno->localizacao,
                'latitude' => $terreno->latitude,
                'longitude' => $terreno->longitude,
                'poligono' => $terreno->poligono,
                'estado' => $terreno->estado,
                'parcelas_count' => $terreno->parcelas_count,
                'updated_at' => optional($terreno->updated_at)?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Terrenos/Index', [
            'terrenos' => $terrenos,
            'filters' => $filters,
            'summary' => [
                'total' => Terreno::query()->count(),
                'ativos' => Terreno::query()->where('estado', 'ativo')->count(),
                'area_total' => (float) Terreno::query()->sum('area_total'),
            ],
            'can' => [
                'create' => $request->user()->can('create', Terreno::class),
                'delete' => $request->user()->hasRole('admin'),
            ],
            'estadoOptions' => $this->estadoOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Terreno::class);

        return Inertia::render('Terrenos/Form', [
            'mode' => 'create',
            'terreno' => null,
            'estadoOptions' => $this->estadoOptions(),
        ]);
    }

    public function edit(Terreno $terreno): Response
    {
        $this->authorize('update', $terreno);

        return Inertia::render('Terrenos/Form', [
            'mode' => 'edit',
            'terreno' => $this->serializeTerreno($terreno),
            'estadoOptions' => $this->estadoOptions(),
        ]);
    }

    public function store(StoreTerrenoRequest $request): RedirectResponse
    {
        $this->authorize('create', Terreno::class);

        try {
            Terreno::query()->create($this->normalizePayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar o terreno. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.terrenos.index')
            ->with('success', 'Terreno criado com sucesso.');
    }

    public function update(UpdateTerrenoRequest $request, Terreno $terreno): RedirectResponse
    {
        $this->authorize('update', $terreno);

        try {
            $terreno->update($this->normalizePayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar o terreno. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.terrenos.index')
            ->with('success', 'Terreno atualizado com sucesso.');
    }

    public function destroy(Terreno $terreno): RedirectResponse
    {
        $this->authorize('delete', $terreno);

        try {
            $terreno->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover o terreno. Confirme se não existem registos associados.', $exception);
        }

        return redirect()
            ->route('app.terrenos.index')
            ->with('success', 'Terreno removido com sucesso.');
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->validated();

        if (array_key_exists('poligono', $data) && empty($data['poligono'])) {
            $data['poligono'] = null;
        }

        return $data;
    }

    private function serializeTerreno(Terreno $terreno): array
    {
        return [
            'id' => $terreno->id,
            'nome' => $terreno->nome,
            'descricao' => $terreno->descricao,
            'area_total' => $terreno->area_total,
            'tipo_solo' => $terreno->tipo_solo,
            'localizacao' => $terreno->localizacao,
            'latitude' => $terreno->latitude,
            'longitude' => $terreno->longitude,
            'poligono' => $terreno->poligono,
            'estado' => $terreno->estado,
        ];
    }

    private function estadoOptions(): array
    {
        return ['ativo', 'inativo', 'em_manutencao'];
    }
}
