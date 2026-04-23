<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlfaiaRequest;
use App\Http\Requests\StoreManutencaoRequest;
use App\Http\Requests\StoreMaquinaRequest;
use App\Http\Requests\UpdateAlfaiaRequest;
use App\Http\Requests\UpdateManutencaoRequest;
use App\Http\Requests\UpdateMaquinaRequest;
use App\Models\Alfaia;
use App\Models\Manutencao;
use App\Models\Maquina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaquinariaManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Maquina::class);
        $this->authorize('viewAny', Alfaia::class);

        $filters = $request->only(['search', 'tipo', 'estado', 'alfaia_estado', 'maquina_id', 'revisao_tipo', 'revisao_maquina_id']);
        $user = $request->user();

        $maquinas = Maquina::query()
            ->withCount(['alfaias', 'operacoes', 'manutencoes'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('marca', 'like', "%{$search}%")
                        ->orWhere('modelo', 'like', "%{$search}%")
                        ->orWhere('matricula', 'like', "%{$search}%")
                        ->orWhere('numero_serie', 'like', "%{$search}%");
                });
            })
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filters['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->orderBy('nome')
            ->paginate(8, ['*'], 'maquinas_page')
            ->withQueryString()
            ->through(fn (Maquina $maquina) => [
                'id' => $maquina->id,
                'nome' => $maquina->nome,
                'tipo' => $maquina->tipo,
                'marca' => $maquina->marca,
                'modelo' => $maquina->modelo,
                'matricula' => $maquina->matricula,
                'numero_serie' => $maquina->numero_serie,
                'ano_aquisicao' => $maquina->ano_aquisicao,
                'horas_uso' => $maquina->horas_uso,
                'horas_manutencao' => $maquina->horas_manutencao,
                'estado' => $maquina->estado,
                'observacoes' => $maquina->observacoes,
                'alfaias_count' => $maquina->alfaias_count,
                'operacoes_count' => $maquina->operacoes_count,
                'manutencoes_count' => $maquina->manutencoes_count,
                'can_update' => $user->can('update', $maquina),
                'can_delete' => $user->can('delete', $maquina),
            ]);

        $alfaias = Alfaia::query()
            ->with('maquina:id,nome,tipo')
            ->withCount('operacoes')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('descricao', 'like', "%{$search}%")
                        ->orWhereHas('maquina', fn ($machineQuery) => $machineQuery->where('nome', 'like', "%{$search}%"));
                });
            })
            ->when($filters['maquina_id'] ?? null, fn ($query, $maquinaId) => $query->where('maquina_id', $maquinaId))
            ->when($filters['alfaia_estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->orderBy('nome')
            ->paginate(6, ['*'], 'alfaias_page')
            ->withQueryString()
            ->through(fn (Alfaia $alfaia) => [
                'id' => $alfaia->id,
                'nome' => $alfaia->nome,
                'tipo' => $alfaia->tipo,
                'maquina_id' => $alfaia->maquina_id,
                'maquina_nome' => $alfaia->maquina?->nome,
                'maquina_tipo' => $alfaia->maquina?->tipo,
                'descricao' => $alfaia->descricao,
                'comprimento' => $alfaia->comprimento,
                'largura' => $alfaia->largura,
                'estado' => $alfaia->estado,
                'observacoes' => $alfaia->observacoes,
                'operacoes_count' => $alfaia->operacoes_count,
                'can_update' => $user->can('update', $alfaia),
                'can_delete' => $user->can('delete', $alfaia),
            ]);

        $revisoes = Manutencao::query()
            ->with('maquina:id,nome,tipo')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('tipo', 'like', "%{$search}%")
                        ->orWhere('descricao', 'like', "%{$search}%")
                        ->orWhere('observacoes', 'like', "%{$search}%")
                        ->orWhereHas('maquina', fn ($machineQuery) => $machineQuery->where('nome', 'like', "%{$search}%"));
                });
            })
            ->when($filters['revisao_tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filters['revisao_maquina_id'] ?? null, fn ($query, $maquinaId) => $query->where('maquina_id', $maquinaId))
            ->orderByDesc('data_manutencao')
            ->paginate(6, ['*'], 'revisoes_page')
            ->withQueryString()
            ->through(fn (Manutencao $revisao) => [
                'id' => $revisao->id,
                'maquina_id' => $revisao->maquina_id,
                'maquina_nome' => $revisao->maquina?->nome,
                'maquina_tipo' => $revisao->maquina?->tipo,
                'data_manutencao' => optional($revisao->data_manutencao)?->format('Y-m-d'),
                'tipo' => $revisao->tipo,
                'descricao' => $revisao->descricao,
                'custo' => $revisao->custo,
                'duracao_minutos' => $revisao->duracao_minutos,
                'proxima_manutencao' => optional($revisao->proxima_manutencao)?->format('Y-m-d'),
                'observacoes' => $revisao->observacoes,
                'can_update' => $revisao->maquina ? $user->can('update', $revisao->maquina) : false,
                'can_delete' => $revisao->maquina ? $user->can('update', $revisao->maquina) : false,
            ]);

        return Inertia::render('Maquinaria/Index', [
            'maquinas' => $maquinas,
            'alfaias' => $alfaias,
            'revisoes' => $revisoes,
            'filters' => $filters,
            'summary' => [
                'maquinas' => Maquina::query()->count(),
                'operacionais' => Maquina::query()->where('estado', 'operacional')->count(),
                'manutencao' => Maquina::query()->where('estado', 'em_manutencao')->count(),
                'alfaias' => Alfaia::query()->count(),
                'alfaias_operacionais' => Alfaia::query()->where('estado', 'operacional')->count(),
                'revisoes' => Manutencao::query()->count(),
                'proximas_revisoes' => Manutencao::query()->whereDate('proxima_manutencao', '>=', now()->toDateString())->count(),
            ],
            'can' => [
                'create_maquina' => $user->can('create', Maquina::class),
                'create_alfaia' => $user->can('create', Alfaia::class),
                'create_revisao' => $user->can('create', Maquina::class),
            ],
            'maquinaTipoOptions' => [
                'trator',
                'ceifeira',
                'distribuidor',
                'pulverizador',
                'carregador',
                'reboque',
                'carro',
                'carrinha',
                'camião',
                'moto_4',
            ],
            'alfaiaTipoOptions' => [
                'charrua',
                'grade de discos',
                'semeadora',
                'distribuidor de adubo',
                'pulverizador',
                'destroçador',
                'niveladora',
                'cultivador',
                'subsolador',
                'triturador de restos',
            ],
            'maquinaEstadoOptions' => ['operacional', 'em_manutencao', 'danificada', 'retirada'],
            'alfaiaEstadoOptions' => ['operacional', 'danificada', 'retirada'],
            'revisaoTipoOptions' => ['revisão', 'preventiva', 'corretiva', 'inspeção'],
            'maquinaOptions' => Maquina::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo', 'estado'])
                ->map(fn (Maquina $maquina) => [
                    'id' => $maquina->id,
                    'nome' => $maquina->nome,
                    'tipo' => $maquina->tipo,
                    'estado' => $maquina->estado,
                ]),
        ]);
    }

    public function storeMaquina(StoreMaquinaRequest $request): RedirectResponse
    {
        $this->authorize('create', Maquina::class);

        try {
            Maquina::query()->create($this->normalizeMaquinaPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a máquina. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Máquina criada com sucesso.');
    }

    public function updateMaquina(UpdateMaquinaRequest $request, Maquina $maquina): RedirectResponse
    {
        $this->authorize('update', $maquina);

        try {
            $maquina->update($this->normalizeMaquinaPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a máquina. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Máquina atualizada com sucesso.');
    }

    public function destroyMaquina(Request $request, Maquina $maquina): RedirectResponse
    {
        $this->authorize('delete', $maquina);

        try {
            $maquina->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a máquina. Confirme se não existem alfaias, operações ou manutenções associadas.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Máquina removida com sucesso.');
    }

    public function storeAlfaia(StoreAlfaiaRequest $request): RedirectResponse
    {
        $this->authorize('create', Alfaia::class);

        try {
            Alfaia::query()->create($this->normalizeAlfaiaPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a alfaia. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Alfaia criada com sucesso.');
    }

    public function updateAlfaia(UpdateAlfaiaRequest $request, Alfaia $alfaia): RedirectResponse
    {
        $this->authorize('update', $alfaia);

        try {
            $alfaia->update($this->normalizeAlfaiaPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a alfaia. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Alfaia atualizada com sucesso.');
    }

    public function destroyAlfaia(Request $request, Alfaia $alfaia): RedirectResponse
    {
        $this->authorize('delete', $alfaia);

        try {
            $alfaia->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a alfaia. Confirme se não existem operações associadas.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Alfaia removida com sucesso.');
    }

    public function storeRevisao(StoreManutencaoRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $maquina = Maquina::query()->findOrFail($validated['maquina_id']);
        $this->authorize('update', $maquina);

        try {
            Manutencao::query()->create($this->normalizeRevisaoPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível guardar a revisão. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Revisão guardada com sucesso.');
    }

    public function updateRevisao(UpdateManutencaoRequest $request, Manutencao $revisao): RedirectResponse
    {
        $this->authorize('update', $revisao->maquina);
        $validated = $request->validated();
        $novaMaquina = Maquina::query()->findOrFail($validated['maquina_id']);
        $this->authorize('update', $novaMaquina);

        try {
            $revisao->update($this->normalizeRevisaoPayload($request));
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a revisão. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Revisão atualizada com sucesso.');
    }

    public function destroyRevisao(Request $request, Manutencao $revisao): RedirectResponse
    {
        $this->authorize('update', $revisao->maquina);

        try {
            $revisao->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a revisão. Tente novamente.', $exception);
        }

        return redirect()
            ->route('app.maquinaria.index', $this->redirectFilters($request))
            ->with('success', 'Revisão removida com sucesso.');
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'tipo', 'estado', 'alfaia_estado', 'maquina_id', 'revisao_tipo', 'revisao_maquina_id']));
    }

    private function normalizeMaquinaPayload(Request $request): array
    {
        $data = $request->validated();

        foreach (['marca', 'modelo', 'matricula', 'numero_serie', 'ano_aquisicao', 'horas_uso', 'horas_manutencao', 'observacoes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (array_key_exists('horas_uso', $data) && $data['horas_uso'] === null) {
            $data['horas_uso'] = 0;
        }

        return $data;
    }

    private function normalizeAlfaiaPayload(Request $request): array
    {
        $data = $request->validated();

        foreach (['maquina_id', 'descricao', 'comprimento', 'largura', 'observacoes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function normalizeRevisaoPayload(Request $request): array
    {
        $data = $request->validated();

        foreach (['custo', 'duracao_minutos', 'proxima_manutencao', 'observacoes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
