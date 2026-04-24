<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTerrenoRequest;
use App\Http\Requests\UpdateTerrenoRequest;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
                'delete' => $request->user()->can('delete', new Terreno()),
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

    public function export(Request $request)
    {
        $this->authorize('viewAny', Terreno::class);

        $terrenos = Terreno::query()
            ->with(['parcelas' => fn ($query) => $query->orderBy('nome')])
            ->orderBy('nome')
            ->get()
            ->map(fn (Terreno $terreno) => [
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
                'parcelas' => $terreno->parcelas->map(fn (Parcela $parcela) => [
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
                ])->values(),
            ])->values();

        $payload = [
            'schema' => 'gestao-agricola.terrenos-parcelas.v1',
            'exported_at' => now()->toIso8601String(),
            'terrenos' => $terrenos,
        ];

        $filename = 'terrenos-parcelas-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Terreno::class);

        $request->validate([
            'ficheiro' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ], [
            'ficheiro.required' => 'Escolha um ficheiro JSON para importar.',
            'ficheiro.file' => 'O ficheiro de importação não é válido.',
            'ficheiro.mimes' => 'O ficheiro deve estar em formato JSON.',
        ]);

        $payload = json_decode(file_get_contents($request->file('ficheiro')->getRealPath()), true);

        if (! is_array($payload) || ! is_array($payload['terrenos'] ?? null)) {
            return back()->with('error', 'O ficheiro não tem o formato esperado para terrenos e parcelas.');
        }

        $importedTerrenos = 0;
        $importedParcelas = 0;

        try {
            DB::transaction(function () use ($payload, &$importedTerrenos, &$importedParcelas) {
                foreach ($payload['terrenos'] as $index => $terrenoData) {
                    $terreno = $this->importTerreno($terrenoData, $index);
                    $importedTerrenos++;

                    foreach (($terrenoData['parcelas'] ?? []) as $parcelaIndex => $parcelaData) {
                        $this->importParcela($parcelaData, $terreno, $index, $parcelaIndex);
                        $importedParcelas++;
                    }
                }
            });
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível importar o ficheiro. Confirme os dados e tente novamente.', $exception);
        }

        return back()->with('success', "Importação concluída: {$importedTerrenos} terrenos e {$importedParcelas} parcelas processados.");
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

    private function importTerreno(array $data, int $index): Terreno
    {
        $payload = $this->normalizeImportPayload($data, [
            'nome',
            'descricao',
            'area_total',
            'tipo_solo',
            'localizacao',
            'latitude',
            'longitude',
            'poligono',
            'estado',
        ]);

        $payload['estado'] = $payload['estado'] ?? 'ativo';

        Validator::make($payload, [
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'area_total' => 'required|numeric|min:0.01',
            'tipo_solo' => 'nullable|string|max:255',
            'localizacao' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'poligono' => 'nullable|array|min:3',
            'poligono.*' => 'array|size:2',
            'poligono.*.0' => 'numeric|between:-90,90',
            'poligono.*.1' => 'numeric|between:-180,180',
            'estado' => 'in:ativo,inativo,em_manutencao',
        ])->validate();

        $terreno = $this->findTerrenoForImport($data);

        if (! $terreno) {
            $terreno = new Terreno();

            if (! empty($data['id']) && ! Terreno::query()->whereKey($data['id'])->exists()) {
                $terreno->id = (int) $data['id'];
            }
        }

        $terreno->forceFill($payload)->save();

        return $terreno;
    }

    private function importParcela(array $data, Terreno $terreno, int $terrenoIndex, int $parcelaIndex): Parcela
    {
        $payload = $this->normalizeImportPayload($data, [
            'nome',
            'numero_parcela',
            'area_total',
            'area_util',
            'descricao',
            'estado',
            'tipo_ocupacao',
            'numero_arvores',
            'compasso_linha_m',
            'compasso_planta_m',
            'latitude',
            'longitude',
            'poligono',
        ]);

        $payload['terreno_id'] = $terreno->id;
        $payload['estado'] = $payload['estado'] ?? 'livre';
        $payload['tipo_ocupacao'] = $payload['tipo_ocupacao'] ?? 'culturas_anuais';

        Validator::make($payload, [
            'terreno_id' => 'required|exists:terrenos,id',
            'nome' => 'required|string|max:255',
            'numero_parcela' => 'nullable|string|max:255',
            'area_total' => 'required|numeric|min:0.01',
            'area_util' => 'nullable|numeric|min:0.01',
            'descricao' => 'nullable|string',
            'estado' => 'in:livre,cultivada,em_preparacao,pousio',
            'tipo_ocupacao' => 'required|in:culturas_anuais,pomar,misto,estufa,outro',
            'numero_arvores' => 'nullable|integer|min:0',
            'compasso_linha_m' => 'nullable|numeric|min:0.01',
            'compasso_planta_m' => 'nullable|numeric|min:0.01',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'poligono' => 'nullable|array|min:3',
            'poligono.*' => 'array|size:2',
            'poligono.*.0' => 'numeric|between:-90,90',
            'poligono.*.1' => 'numeric|between:-180,180',
        ])->validate();

        $parcela = $this->findParcelaForImport($data, $terreno);

        if (! $parcela) {
            $parcela = new Parcela();

            if (! empty($data['id']) && ! Parcela::query()->whereKey($data['id'])->exists()) {
                $parcela->id = (int) $data['id'];
            }
        }

        $parcela->forceFill($payload)->save();

        return $parcela;
    }

    private function findTerrenoForImport(array $data): ?Terreno
    {
        if (! empty($data['id'])) {
            $terreno = Terreno::query()->find($data['id']);

            if ($terreno) {
                return $terreno;
            }
        }

        return Terreno::query()
            ->where('nome', $data['nome'] ?? '')
            ->first();
    }

    private function findParcelaForImport(array $data, Terreno $terreno): ?Parcela
    {
        if (! empty($data['id'])) {
            $parcela = Parcela::query()->find($data['id']);

            if ($parcela) {
                return $parcela;
            }
        }

        return Parcela::query()
            ->where('terreno_id', $terreno->id)
            ->when(
                ! empty($data['numero_parcela']),
                fn ($query) => $query->where('numero_parcela', $data['numero_parcela']),
                fn ($query) => $query->where('nome', $data['nome'] ?? ''),
            )
            ->first();
    }

    private function normalizeImportPayload(array $data, array $fields): array
    {
        $payload = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        foreach (['area_total', 'area_util', 'latitude', 'longitude', 'compasso_linha_m', 'compasso_planta_m'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = str_replace(',', '.', $payload[$field]);
            }
        }

        if (array_key_exists('poligono', $payload) && empty($payload['poligono'])) {
            $payload['poligono'] = null;
        }

        return $payload;
    }
}
