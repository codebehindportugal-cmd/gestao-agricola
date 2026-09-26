<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Manutencao;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Revisoes e reparacoes, pelo chat.
 *
 * Uma manutencao tem maquina, alfaia, ou as duas (a revisao do conjunto): o
 * que nao pode e nao ter nenhuma. A peca comprada e uma fatura (POST
 * /faturas com `alfaia`); isto e o trabalho feito.
 *
 * Nao ha idempotencia por referencia externa — a tabela nao tem essa coluna.
 * Reenviar cria outra revisao; para corrigir, PATCH ou DELETE.
 */
class ManutencaoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $dados = $this->validar($request, true);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $manutencao = Manutencao::query()->create($dados);

        return $this->criado($this->formatar($manutencao->fresh(['maquina', 'alfaia'])));
    }

    public function update(Request $request, Manutencao $manutencao): JsonResponse
    {
        try {
            $dados = $this->validar($request, false);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $manutencao->update($dados);

        return $this->ok($this->formatar($manutencao->fresh(['maquina', 'alfaia'])));
    }

    public function destroy(Manutencao $manutencao): JsonResponse
    {
        $manutencao->delete();

        return $this->ok([
            'manutencao_id' => $manutencao->id,
            'estado' => 'arquivada',
            'restaurar_em' => "POST /api/v1/manutencoes/{$manutencao->id}/restaurar",
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $manutencao = Manutencao::withTrashed()->findOrFail($id);

        if (! $manutencao->trashed()) {
            return $this->ok($this->formatar($manutencao), ['a manutencao nao estava arquivada.']);
        }

        $manutencao->restore();

        return $this->ok($this->formatar($manutencao->fresh(['maquina', 'alfaia'])));
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $aCriar): array
    {
        $obrigatorio = $aCriar ? 'required' : 'sometimes';

        $dados = $request->validate([
            'maquina' => ['sometimes', 'nullable'],
            'alfaia' => ['sometimes', 'nullable'],
            'data' => [$obrigatorio, 'date'],
            'tipo' => [$obrigatorio, 'string', 'max:255'],
            'descricao' => [$obrigatorio, 'string'],
            'custo' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duracao_minutos' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'proxima_manutencao' => ['sometimes', 'nullable', 'date'],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ]);

        foreach (['maquina' => 'resolverMaquina', 'alfaia' => 'resolverAlfaia'] as $campo => $metodo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }

            $valor = $dados[$campo];
            unset($dados[$campo]);

            $dados[$campo.'_id'] = ($valor === null || $valor === '')
                ? null
                : $this->resolvedor->{$metodo}($valor)->id;
        }

        if (array_key_exists('data', $dados)) {
            $dados['data_manutencao'] = $dados['data'];
            unset($dados['data']);
        }

        if ($aCriar && blank($dados['maquina_id'] ?? null) && blank($dados['alfaia_id'] ?? null)) {
            throw ValidationException::withMessages([
                'maquina' => ['Indique a maquina, a alfaia, ou as duas.'],
            ]);
        }

        return $dados;
    }

    /** @return array<string, mixed> */
    private function formatar(Manutencao $manutencao): array
    {
        return [
            'manutencao' => [
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
            ],
        ];
    }
}
