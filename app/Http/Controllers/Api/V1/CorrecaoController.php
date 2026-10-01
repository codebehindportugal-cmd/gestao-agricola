<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Colheita;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Receita;
use App\Services\MovimentoStockService;
use App\Services\ResolvedorReferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Corrigir e apagar o que foi mal registado.
 *
 * O POST das faturas e idempotente pelo numero: reenviar devolve o que ja la
 * esta e nao corrige nada. Ate aqui a unica saida era ir ao ecra apagar a
 * despesa a mao — o que nao da para fazer pelo chat. Estes endpoints fecham
 * esse buraco.
 *
 * Tudo o que se apaga aqui e soft delete e tem restauro: um engano nao pode
 * custar o registo, sobretudo num caderno de campo que a lei obriga a manter.
 * Apagar uma fatura desfaz o que ela fez — as entradas em stock e o custo que
 * criou — e restaura-la volta a faze-lo.
 */
class CorrecaoController extends Controller
{
    use RespondeJson;

    public function __construct(
        private readonly ResolvedorReferencias $resolvedor,
        private readonly MovimentoStockService $stock
    ) {
    }

    // ── Faturas / despesas ───────────────────────────────────────────────────

    /**
     * PATCH /api/v1/faturas/{despesa}
     *
     * Cabecalho da fatura, nao as linhas: mexer nas linhas mexe no stock e no
     * catalogo, e isso faz-se apagando e voltando a enviar, que passa pelo
     * mesmo caminho do registo normal.
     */
    public function atualizarFatura(Request $request, Despesa $despesa): JsonResponse
    {
        try {
            $dados = $this->validar($request, [
                'titulo' => ['sometimes', 'string', 'max:255'],
                'fornecedor' => ['sometimes', 'nullable', 'string', 'max:255'],
                'numero_fatura' => ['sometimes', 'nullable', 'string', 'max:255'],
                'data' => ['sometimes', 'date'],
                'valor' => ['sometimes', 'numeric', 'min:0'],
                'categoria' => ['sometimes', 'string'],
                'notas' => ['sometimes', 'nullable', 'string'],
                'campanha' => ['sometimes', 'nullable'],
                'maquina' => ['sometimes', 'nullable'],
                'alfaia' => ['sometimes', 'nullable'],
            ]);

            $dados = $this->resolverLigacoes($dados);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $despesa->update($dados);

        // O Custo que a fatura criou e a mesma despesa vista pela tesouraria:
        // deixa-lo para tras dava dois numeros diferentes para o mesmo papel.
        $custo = $this->custoDaFatura($despesa);

        if ($custo !== null) {
            // Por chave presente e nao por valor nao-nulo: desligar a campanha
            // da fatura tem de desligar a do custo, e um array_filter comia o
            // null e deixava a ligacao antiga de pe.
            $atualizacoes = [];

            if (array_key_exists('valor', $dados)) {
                $atualizacoes['valor'] = $dados['valor'];
            }

            if (array_key_exists('data', $dados)) {
                $atualizacoes['data_custo'] = $dados['data'];
            }

            foreach (['campanha_id', 'maquina_id', 'alfaia_id'] as $coluna) {
                if (array_key_exists($coluna, $dados)) {
                    $atualizacoes[$coluna] = $dados[$coluna];
                }
            }

            if ($atualizacoes !== []) {
                $custo->update($atualizacoes);
            }
        }

        return $this->ok($this->formatarDespesa($despesa->fresh(['campanha', 'maquina', 'alfaia'])));
    }

    /** DELETE /api/v1/faturas/{despesa} — desfaz o stock e o custo, e arquiva. */
    public function apagarFatura(Despesa $despesa): JsonResponse
    {
        $avisos = [];

        DB::transaction(function () use ($despesa, &$avisos) {
            $this->stock->reverterEntradas($despesa);
            $avisos[] = 'as entradas em stock desta fatura foram revertidas.';

            $custo = $this->custoDaFatura($despesa);

            if ($custo !== null) {
                $custo->delete();
                $avisos[] = "o custo #{$custo->id} foi arquivado com a fatura.";
            }

            // O ficheiro fica: e a prova da compra, e restaurar sem ele deixava
            // o caderno de campo coxo. So sai quando a despesa for apagada de
            // vez no ecra.
            $despesa->delete();
        });

        return $this->ok([
            'despesa_id' => $despesa->id,
            'numero_fatura' => $despesa->numero_fatura,
            'estado' => 'arquivada',
            'restaurar_em' => "POST /api/v1/faturas/{$despesa->id}/restaurar",
        ], $avisos);
    }

    /** POST /api/v1/faturas/{id}/restaurar — volta a por a fatura e o stock. */
    public function restaurarFatura(int $id): JsonResponse
    {
        $despesa = Despesa::withTrashed()->findOrFail($id);

        if (! $despesa->trashed()) {
            return $this->ok($this->formatarDespesa($despesa), ['a fatura nao estava arquivada.']);
        }

        $avisos = [];

        DB::transaction(function () use ($despesa, &$avisos) {
            $despesa->restore();

            Custo::withTrashed()
                ->where('referencia_externa', 'fatura-'.$despesa->id)
                ->get()
                ->each(function (Custo $custo) use (&$avisos) {
                    $custo->restore();
                    $avisos[] = "o custo #{$custo->id} foi restaurado.";
                });

            $despesa->load(['items.produto']);
            $movimentos = $this->stock->processarEntradas($despesa);

            if ($movimentos !== []) {
                $avisos[] = count($movimentos).' entrada(s) em stock repostas.';
            }
        });

        return $this->ok($this->formatarDespesa($despesa->fresh(['campanha', 'maquina', 'alfaia'])), $avisos);
    }

    // ── Custos ───────────────────────────────────────────────────────────────

    public function atualizarCusto(Request $request, Custo $custo): JsonResponse
    {
        try {
            $dados = $this->validar($request, [
                'descricao' => ['sometimes', 'string', 'max:255'],
                'tipo' => ['sometimes', 'string', 'max:50'],
                'valor' => ['sometimes', 'numeric', 'min:0'],
                'data' => ['sometimes', 'date'],
                'rateavel' => ['sometimes', 'boolean'],
                'base_rateio' => ['sometimes', 'nullable', 'string'],
                'campanha' => ['sometimes', 'nullable'],
                'cultura' => ['sometimes', 'nullable'],
                'parcela' => ['sometimes', 'nullable'],
                'maquina' => ['sometimes', 'nullable'],
                'alfaia' => ['sometimes', 'nullable'],
                // Passar o custo para outra operacao (ex.: juntar duas apanhas numa).
                'operacao' => ['sometimes', 'nullable'],
                'observacoes' => ['sometimes', 'nullable', 'string'],
            ]);

            $dados = $this->resolverLigacoes($dados);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        if (array_key_exists('data', $dados)) {
            $dados['data_custo'] = $dados['data'];
            unset($dados['data']);
        }

        $custo->update($dados);

        return $this->ok(['custo' => $custo->fresh()->only([
            'id', 'descricao', 'tipo', 'valor', 'data_custo', 'campanha_id',
            'cultura_id', 'parcela_id', 'maquina_id', 'alfaia_id', 'rateavel', 'base_rateio',
            'operacao_id',
        ])]);
    }

    public function apagarCusto(Custo $custo): JsonResponse
    {
        $avisos = [];

        if ($custo->operacao_id !== null) {
            $avisos[] = 'este custo pertence a uma operacao; o custo_real dela fica como estava.';
        }

        $custo->delete();

        return $this->ok([
            'custo_id' => $custo->id,
            'estado' => 'arquivado',
            'restaurar_em' => "POST /api/v1/custos/{$custo->id}/restaurar",
        ], $avisos);
    }

    public function restaurarCusto(int $id): JsonResponse
    {
        return $this->restaurar(Custo::withTrashed()->findOrFail($id), 'custo');
    }

    // ── Colheitas ────────────────────────────────────────────────────────────

    public function atualizarColheita(Request $request, Colheita $colheita): JsonResponse
    {
        try {
            $dados = $this->validar($request, [
                'quantidade_total' => ['sometimes', 'numeric', 'min:0'],
                'quantidade_perdas' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'unidade_medida' => ['sometimes', 'string', 'max:20'],
                'qualidade' => ['sometimes', 'nullable', 'string', 'max:50'],
                'data_colheita' => ['sometimes', 'date'],
                'campanha' => ['sometimes', 'nullable'],
                'cultura' => ['sometimes', 'nullable'],
                'parcela' => ['sometimes', 'nullable'],
                // A apanha a que esta colheita pertence; o custo dela reparte-se pelos kg.
                'operacao' => ['sometimes', 'nullable'],
                'observacoes' => ['sometimes', 'nullable', 'string'],
            ]);

            $dados = $this->resolverLigacoes($dados);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $colheita->update($dados);

        return $this->ok(['colheita' => $colheita->fresh()->only([
            'id', 'data_colheita', 'quantidade_total', 'unidade_medida',
            'campanha_id', 'cultura_id', 'parcela_id', 'operacao_id',
        ])]);
    }

    public function apagarColheita(Colheita $colheita): JsonResponse
    {
        $colheita->delete();

        return $this->ok([
            'colheita_id' => $colheita->id,
            'estado' => 'arquivada',
            'restaurar_em' => "POST /api/v1/colheitas/{$colheita->id}/restaurar",
        ], ['os quilos desta colheita deixam de contar no custo/kg da campanha.']);
    }

    public function restaurarColheita(int $id): JsonResponse
    {
        return $this->restaurar(Colheita::withTrashed()->findOrFail($id), 'colheita');
    }

    // ── Receitas (vendas) ────────────────────────────────────────────────────

    public function atualizarReceita(Request $request, Receita $receita): JsonResponse
    {
        try {
            $dados = $this->validar($request, [
                'descricao' => ['sometimes', 'string', 'max:255'],
                'tipo' => ['sometimes', 'string', 'max:50'],
                'valor' => ['sometimes', 'numeric', 'min:0'],
                'quantidade' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'unidade' => ['sometimes', 'nullable', 'string', 'max:20'],
                'preco_unitario' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'data' => ['sometimes', 'date'],
                'comprador_nome' => ['sometimes', 'nullable', 'string', 'max:255'],
                'documento' => ['sometimes', 'nullable', 'string', 'max:255'],
                'campanha' => ['sometimes', 'nullable'],
                'cultura' => ['sometimes', 'nullable'],
                'parcela' => ['sometimes', 'nullable'],
                'observacoes' => ['sometimes', 'nullable', 'string'],
            ]);

            $dados = $this->resolverLigacoes($dados);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $receita->update($dados);

        return $this->ok(['receita' => $receita->fresh()->only([
            'id', 'descricao', 'tipo', 'valor', 'quantidade', 'preco_unitario',
            'data', 'campanha_id', 'cultura_id', 'parcela_id',
        ])]);
    }

    public function apagarReceita(Receita $receita): JsonResponse
    {
        $receita->delete();

        return $this->ok([
            'receita_id' => $receita->id,
            'estado' => 'arquivada',
            'restaurar_em' => "POST /api/v1/receitas/{$receita->id}/restaurar",
        ]);
    }

    public function restaurarReceita(int $id): JsonResponse
    {
        return $this->restaurar(Receita::withTrashed()->findOrFail($id), 'receita');
    }

    // ── Apoio ────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $regras
     * @return array<string, mixed>
     */
    private function validar(Request $request, array $regras): array
    {
        $dados = $request->validate($regras);

        if ($dados === []) {
            throw ValidationException::withMessages([
                'corpo' => ['Nao veio nenhum campo para alterar.'],
            ]);
        }

        return $dados;
    }

    /**
     * Troca `campanha`, `cultura`, `parcela`, `maquina` e `alfaia` (id ou nome)
     * pelas colunas correspondentes. Enviar null desliga a ligacao.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function resolverLigacoes(array $dados): array
    {
        $mapa = [
            'campanha' => ['campanha_id', 'resolverCampanha'],
            'cultura' => ['cultura_id', 'resolverCultura'],
            'parcela' => ['parcela_id', 'resolverParcela'],
            'maquina' => ['maquina_id', 'resolverMaquina'],
            'alfaia' => ['alfaia_id', 'resolverAlfaia'],
            'operacao' => ['operacao_id', 'resolverOperacao'],
        ];

        foreach ($mapa as $campo => [$coluna, $metodo]) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }

            $valor = $dados[$campo];
            unset($dados[$campo]);

            $dados[$coluna] = ($valor === null || $valor === '')
                ? null
                : $this->resolvedor->{$metodo}($valor)->id;
        }

        return $dados;
    }

    private function custoDaFatura(Despesa $despesa): ?Custo
    {
        return Custo::query()->where('referencia_externa', 'fatura-'.$despesa->id)->first();
    }

    /** @return array<string, mixed> */
    private function formatarDespesa(Despesa $despesa): array
    {
        return [
            'despesa' => [
                'id' => $despesa->id,
                'titulo' => $despesa->titulo,
                'numero_fatura' => $despesa->numero_fatura,
                'fornecedor' => $despesa->fornecedor,
                'categoria' => $despesa->categoria,
                'valor' => (float) $despesa->valor,
                'data' => $despesa->data?->toDateString(),
                'campanha' => $despesa->campanha?->nome_completo,
                'equipamento' => $despesa->equipamento_nome,
                'ficheiro_url' => $despesa->ficheiro_path
                    ? Storage::disk('public')->url($despesa->ficheiro_path)
                    : null,
            ],
        ];
    }

    private function restaurar(object $modelo, string $nome): JsonResponse
    {
        if (! $modelo->trashed()) {
            return $this->ok([$nome.'_id' => $modelo->id, 'estado' => 'activo'], ["o {$nome} nao estava arquivado."]);
        }

        $modelo->restore();

        return $this->ok([$nome.'_id' => $modelo->id, 'estado' => 'restaurado']);
    }
}
