<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Campanha;
use App\Models\Equipa;
use App\Models\Funcionario;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de leitura do cadastro, para clientes externos resolverem
 * nomes -> ids antes de escrever (campanhas, funcionarios, equipas, produtos).
 */
class CatalogoController extends Controller
{
    use RespondeJson;

    public function campanhas(Request $request): JsonResponse
    {
        $query = Campanha::query()->with('cultura:id,nome,tipo');

        if ($ano = $request->query('ano')) {
            $query->where('ano', $ano);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $campanhas = $query->orderByDesc('ano')->orderBy('id')->get()
            ->map(fn (Campanha $campanha) => [
                'id' => $campanha->id,
                'nome' => trim(($campanha->cultura?->nome ? $campanha->cultura->nome.' ' : '').$campanha->ano),
                'ano' => $campanha->ano,
                'status' => $campanha->status,
                'data_inicio' => $campanha->data_inicio?->toDateString(),
                'data_fim' => $campanha->data_fim?->toDateString(),
                'cultura' => $campanha->cultura ? [
                    'id' => $campanha->cultura->id,
                    'nome' => $campanha->cultura->nome,
                    'tipo' => $campanha->cultura->tipo,
                ] : null,
            ])->values();

        return $this->ok(['campanhas' => $campanhas->all()]);
    }

    public function funcionarios(Request $request): JsonResponse
    {
        $query = Funcionario::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($termo = $request->query('q')) {
            $query->where('nome', 'like', '%'.$termo.'%');
        }

        $funcionarios = $query->orderBy('nome')->get()
            ->map(fn (Funcionario $funcionario) => [
                'id' => $funcionario->id,
                'nome' => $funcionario->nome,
                'cargo' => $funcionario->cargo,
                'status' => $funcionario->status,
                'tipo_contrato' => $funcionario->tipo_contrato,
                'valor_hora' => $funcionario->valor_hora,
                'aplicador_numero_autorizacao' => $funcionario->aplicador_numero_autorizacao,
            ])->values();

        return $this->ok(['funcionarios' => $funcionarios->all()]);
    }

    public function equipas(Request $request): JsonResponse
    {
        $equipas = Equipa::query()
            ->with(['funcionarios:id,nome,valor_hora', 'lider:id,nome'])
            ->orderBy('nome')
            ->get()
            ->map(fn (Equipa $equipa) => [
                'id' => $equipa->id,
                'nome' => $equipa->nome,
                'status' => $equipa->status,
                'lider' => $equipa->lider ? ['id' => $equipa->lider->id, 'nome' => $equipa->lider->nome] : null,
                'funcionarios' => $equipa->funcionarios->map(fn ($f) => [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'valor_hora' => $f->valor_hora,
                ])->values()->all(),
            ])->values();

        return $this->ok(['equipas' => $equipas->all()]);
    }

    public function produtos(Request $request): JsonResponse
    {
        $query = Produto::query();

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($termo = $request->query('q')) {
            $query->where(function ($q) use ($termo): void {
                $q->where('nome', 'like', '%'.$termo.'%')
                    ->orWhere('numero_autorizacao_dgav', 'like', '%'.$termo.'%')
                    ->orWhere('codigo_interno', 'like', '%'.$termo.'%');
            });
        }

        $produtos = $query->orderBy('nome')->get()
            ->map(fn (Produto $produto) => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'tipo' => $produto->tipo,
                'codigo_interno' => $produto->codigo_interno,
                'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                'custo_unitario' => $produto->custo_unitario,
                'unidade_medida' => $produto->unidade_medida,
                'estabelecimento_venda_nome' => $produto->estabelecimento_venda_nome,
                'conforme_dgav' => ! $produto->ehFitofarmaco() || filled($produto->numero_autorizacao_dgav),
            ])->values();

        return $this->ok(['produtos' => $produtos->all()]);
    }
}
