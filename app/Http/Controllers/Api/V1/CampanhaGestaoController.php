<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Campanha;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * Criar, fechar e unificar campanhas sem entrar no servidor.
 *
 * A unificacao era um comando artisan que so corria por SSH. Aqui corre pela
 * API, e — como na linha de comandos — sem `confirmar: true` devolve apenas o
 * plano: o texto que diz o que vai acontecer, sem alterar nada.
 */
class CampanhaGestaoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $dados = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'inicio' => ['required', 'date'],
                'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
                'ano' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'status' => ['nullable', 'string', 'in:planejada,em_curso,concluida,cancelada'],
                'observacoes' => ['nullable', 'string'],
                'parcelas' => ['nullable', 'array'],
                'parcelas.*' => ['required'],
            ]);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $existente = Campanha::query()->where('nome', $dados['nome'])->first();

        if ($existente !== null) {
            return $this->criado(
                $this->formatar($existente),
                ["ja existia uma campanha com o nome {$dados['nome']}; nao foi criada outra."]
            );
        }

        $campanha = Campanha::query()->create([
            'nome' => $dados['nome'],
            'cultura_id' => null,
            'ano' => $dados['ano'] ?? Carbon::parse($dados['fim'] ?? $dados['inicio'])->year,
            'data_inicio' => $dados['inicio'],
            'data_fim' => $dados['fim'] ?? null,
            'status' => $dados['status'] ?? 'em_curso',
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        $avisos = $this->ligarParcelas($campanha, $dados['parcelas'] ?? []);

        return $this->criado($this->formatar($campanha->fresh('parcelas')), $avisos);
    }

    public function update(Request $request, Campanha $campanha): JsonResponse
    {
        try {
            $dados = $request->validate([
                'nome' => ['sometimes', 'string', 'max:255'],
                'inicio' => ['sometimes', 'date'],
                'fim' => ['sometimes', 'nullable', 'date'],
                'status' => ['sometimes', 'string', 'in:planejada,em_curso,concluida,cancelada'],
                'observacoes' => ['sometimes', 'nullable', 'string'],
                'parcelas' => ['sometimes', 'array'],
                'parcelas.*' => ['required'],
            ]);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $campanha->update(array_filter([
            'nome' => $dados['nome'] ?? null,
            'data_inicio' => $dados['inicio'] ?? null,
            'status' => $dados['status'] ?? null,
        ], fn ($valor) => $valor !== null) + (
            array_key_exists('fim', $dados) ? ['data_fim' => $dados['fim']] : []
        ) + (
            array_key_exists('observacoes', $dados) ? ['observacoes' => $dados['observacoes']] : []
        ));

        $avisos = array_key_exists('parcelas', $dados)
            ? $this->ligarParcelas($campanha, $dados['parcelas'])
            : [];

        return $this->ok($this->formatar($campanha->fresh('parcelas')), $avisos);
    }

    /**
     * POST /api/v1/campanhas/unificar
     *
     * Sem `confirmar` devolve o plano. E o mesmo agri:unificar-campanhas, pelo
     * que as regras sao as dele: so arquiva/apaga o que ficar vazio, apaga de
     * vez apenas as campanhas que a aplicacao inventou, e corre em transaccao.
     */
    public function unificar(Request $request): JsonResponse
    {
        try {
            $dados = $request->validate([
                'nome' => ['nullable', 'string', 'max:255'],
                'inicio' => ['nullable', 'date'],
                'fim' => ['nullable', 'date'],
                'campanhas' => ['nullable', 'string'],
                'apagar' => ['nullable', 'boolean'],
                'manter_rateaveis' => ['nullable', 'boolean'],
                'confirmar' => ['nullable', 'boolean'],
            ]);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $opcoes = array_filter([
            '--nome' => $dados['nome'] ?? null,
            '--inicio' => $dados['inicio'] ?? null,
            '--fim' => $dados['fim'] ?? null,
            '--campanhas' => $dados['campanhas'] ?? null,
        ], fn ($valor) => $valor !== null);

        foreach (['apagar' => '--apagar', 'manter_rateaveis' => '--manter-rateaveis', 'confirmar' => '--confirmar'] as $campo => $opcao) {
            if (! empty($dados[$campo])) {
                $opcoes[$opcao] = true;
            }
        }

        $codigo = Artisan::call('agri:unificar-campanhas', $opcoes);
        $saida = Artisan::output();

        $avisos = empty($dados['confirmar'])
            ? ['plano apenas: nada foi alterado. Repetir com "confirmar": true para aplicar.']
            : [];

        return $this->ok([
            'aplicado' => (bool) ($dados['confirmar'] ?? false),
            'codigo' => $codigo,
            'relatorio' => $saida,
            'campanhas' => Campanha::query()
                ->orderBy('id')
                ->get(['id', 'nome', 'cultura_id', 'ano', 'data_inicio', 'data_fim', 'status'])
                ->map(fn (Campanha $campanha) => [
                    'id' => $campanha->id,
                    'nome' => $campanha->nome_completo,
                    'inicio' => $campanha->data_inicio?->toDateString(),
                    'fim' => $campanha->data_fim?->toDateString(),
                    'status' => $campanha->status,
                ])
                ->all(),
        ], $avisos);
    }

    /**
     * @param  array<int, mixed>  $referencias
     * @return array<int, string>
     */
    private function ligarParcelas(Campanha $campanha, array $referencias): array
    {
        if ($referencias === []) {
            return [];
        }

        $ids = [];
        $avisos = [];

        foreach ($referencias as $referencia) {
            try {
                $ids[] = $this->resolvedor->resolverParcela($referencia)->id;
            } catch (ValidationException $excepcao) {
                $avisos[] = "parcela nao encontrada e ignorada: {$referencia}";
            }
        }

        if ($ids !== []) {
            $campanha->parcelas()->syncWithoutDetaching($ids);
            $avisos[] = count($ids).' parcela(s) ligadas a campanha.';
        }

        return $avisos;
    }

    /** @return array<string, mixed> */
    private function formatar(Campanha $campanha): array
    {
        return [
            'campanha' => [
                'id' => $campanha->id,
                'nome' => $campanha->nome_completo,
                'ano' => $campanha->ano,
                'inicio' => $campanha->data_inicio?->toDateString(),
                'fim' => $campanha->data_fim?->toDateString(),
                'status' => $campanha->status,
                'parcelas' => $campanha->relationLoaded('parcelas')
                    ? $campanha->parcelas->pluck('nome')->all()
                    : null,
            ],
        ];
    }
}
