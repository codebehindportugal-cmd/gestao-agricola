<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipaRequest;
use App\Http\Requests\StoreFuncionarioRequest;
use App\Http\Requests\UpdateEquipaRequest;
use App\Http\Requests\UpdateFuncionarioRequest;
use App\Models\Equipa;
use App\Models\Funcionario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaoObraManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Funcionario::class);
        $this->authorize('viewAny', Equipa::class);

        $filters = $request->only(['search', 'status', 'tipo_contrato', 'equipa_status']);
        $user = $request->user();

        $funcionarios = Funcionario::query()
            ->with('equipas:id,nome,status')
            ->withCount(['jornadas', 'equipas'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('telefone', 'like', "%{$search}%")
                        ->orWhere('cargo', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['tipo_contrato'] ?? null, fn ($query, $tipo) => $query->where('tipo_contrato', $tipo))
            ->orderBy('nome')
            ->paginate(8, ['*'], 'funcionarios_page')
            ->withQueryString()
            ->through(fn (Funcionario $funcionario) => [
                'id' => $funcionario->id,
                'nome' => $funcionario->nome,
                'email' => $funcionario->email,
                'telefone' => $funcionario->telefone,
                'cargo' => $funcionario->cargo,
                'aplicador_numero_autorizacao' => $funcionario->aplicador_numero_autorizacao,
                'data_admissao' => optional($funcionario->data_admissao)?->format('Y-m-d'),
                'data_saida' => optional($funcionario->data_saida)?->format('Y-m-d'),
                'tipo_contrato' => $funcionario->tipo_contrato,
                'valor_hora' => $funcionario->valor_hora,
                'status' => $funcionario->status,
                'observacoes' => $funcionario->observacoes,
                'jornadas_count' => $funcionario->jornadas_count,
                'equipas_count' => $funcionario->equipas_count,
                'equipas' => $funcionario->equipas->map(fn (Equipa $equipa) => [
                    'id' => $equipa->id,
                    'nome' => $equipa->nome,
                    'status' => $equipa->status,
                ])->values(),
                'can_update' => $user->can('update', $funcionario),
                'can_delete' => $user->can('delete', $funcionario),
            ]);

        $equipas = Equipa::query()
            ->with(['lider:id,nome', 'funcionarios:id,nome,cargo,status'])
            ->withCount('funcionarios')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('descricao', 'like', "%{$search}%")
                        ->orWhereHas('lider', fn ($leaderQuery) => $leaderQuery->where('nome', 'like', "%{$search}%"));
                });
            })
            ->when($filters['equipa_status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('nome')
            ->paginate(6, ['*'], 'equipas_page')
            ->withQueryString()
            ->through(fn (Equipa $equipa) => [
                'id' => $equipa->id,
                'nome' => $equipa->nome,
                'lider_id' => $equipa->lider_id,
                'lider_nome' => $equipa->lider?->nome,
                'descricao' => $equipa->descricao,
                'status' => $equipa->status,
                'funcionarios_count' => $equipa->funcionarios_count,
                'funcionario_ids' => $equipa->funcionarios->pluck('id')->map(fn ($id) => (string) $id)->values(),
                'funcionarios' => $equipa->funcionarios->map(fn (Funcionario $funcionario) => [
                    'id' => $funcionario->id,
                    'nome' => $funcionario->nome,
                    'cargo' => $funcionario->cargo,
                    'status' => $funcionario->status,
                ])->values(),
                'can_update' => $user->can('update', $equipa),
                'can_delete' => $user->can('delete', $equipa),
            ]);

        return Inertia::render('MaoObra/Index', [
            'funcionarios' => $funcionarios,
            'equipas' => $equipas,
            'filters' => $filters,
            'summary' => [
                'funcionarios' => Funcionario::query()->count(),
                'ativos' => Funcionario::query()->where('status', 'ativo')->count(),
                'em_licenca' => Funcionario::query()->where('status', 'em_licenca')->count(),
                'equipas' => Equipa::query()->count(),
                'equipas_ativas' => Equipa::query()->where('status', 'ativa')->count(),
            ],
            'can' => [
                'create_funcionario' => $user->can('create', Funcionario::class),
                'create_equipa' => $user->can('create', Equipa::class),
            ],
            'statusOptions' => ['ativo', 'inativo', 'em_licenca'],
            'contratoOptions' => ['permanente', 'temporario', 'estagiario'],
            'equipaStatusOptions' => ['ativa', 'inativa'],
            'funcionarioOptions' => Funcionario::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'cargo', 'status', 'aplicador_numero_autorizacao'])
                ->map(fn (Funcionario $funcionario) => [
                    'id' => $funcionario->id,
                    'nome' => $funcionario->nome,
                    'cargo' => $funcionario->cargo,
                    'status' => $funcionario->status,
                    'aplicador_numero_autorizacao' => $funcionario->aplicador_numero_autorizacao,
                ]),
        ]);
    }

    public function storeFuncionario(StoreFuncionarioRequest $request): RedirectResponse
    {
        $this->authorize('create', Funcionario::class);

        try {
            Funcionario::query()->create($request->validated());
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar o trabalhador. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Trabalhador criado com sucesso.');
    }

    public function updateFuncionario(UpdateFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorize('update', $funcionario);

        try {
            $funcionario->update($request->validated());
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar o trabalhador. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Trabalhador atualizado com sucesso.');
    }

    public function destroyFuncionario(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorize('delete', $funcionario);

        try {
            $funcionario->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover o trabalhador. Confirme se não existem jornadas ou equipas associadas.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Trabalhador removido com sucesso.');
    }

    public function storeEquipa(StoreEquipaRequest $request): RedirectResponse
    {
        $this->authorize('create', Equipa::class);

        $data = $request->validated();
        $funcionarioIds = $data['funcionario_ids'] ?? [];
        unset($data['funcionario_ids']);

        try {
            $equipa = Equipa::query()->create($data);
            $equipa->funcionarios()->sync($funcionarioIds);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a equipa. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Equipa criada com sucesso.');
    }

    public function updateEquipa(UpdateEquipaRequest $request, Equipa $equipa): RedirectResponse
    {
        $this->authorize('update', $equipa);

        $data = $request->validated();
        $funcionarioIds = $data['funcionario_ids'] ?? [];
        unset($data['funcionario_ids']);

        try {
            $equipa->update($data);
            $equipa->funcionarios()->sync($funcionarioIds);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a equipa. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Equipa atualizada com sucesso.');
    }

    public function destroyEquipa(Request $request, Equipa $equipa): RedirectResponse
    {
        $this->authorize('delete', $equipa);

        try {
            $equipa->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a equipa. Confirme se não existem registos associados.', $exception);
        }

        return redirect()
            ->route('app.mao-obra.index', $this->redirectFilters($request))
            ->with('success', 'Equipa removida com sucesso.');
    }

    private function redirectFilters(Request $request): array
    {
        return array_filter($request->only(['search', 'status', 'tipo_contrato', 'equipa_status']));
    }
}
