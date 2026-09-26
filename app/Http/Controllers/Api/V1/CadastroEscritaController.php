<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Armazem;
use App\Models\Equipa;
use App\Models\Fornecedor;
use App\Models\Funcionario;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Stock;
use App\Services\ResolvedorReferencias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * O cadastro que faltava: pessoas, fornecedores, armazens, produtos e stock.
 *
 * Terrenos, parcelas, culturas, maquinas e alfaias ja tinham escrita pela API.
 * Faltava o resto — e sobretudo o stock, que so mexia sozinho pelas faturas e
 * pelas operacoes: nao havia forma de dizer "contei o armazem e ha 8 litros".
 */
class CadastroEscritaController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ResolvedorReferencias $resolvedor)
    {
    }

    /**
     * POST /api/v1/stock/ajustes
     *
     * tipo=inventario: a quantidade passa a ser a contada, e o movimento
     * registado e a diferenca — e o que se quer depois de contar o armazem.
     * tipo=entrada/saida: soma ou subtrai.
     *
     * Ha sempre um MovimentoStock, mesmo num acerto para baixo: um stock que
     * muda sem rasto e um stock em que ninguem acredita.
     */
    public function ajustarStock(Request $request): JsonResponse
    {
        try {
            $dados = $request->validate([
                'produto' => ['required'],
                'tipo' => ['required', 'string', Rule::in(['entrada', 'saida', 'inventario'])],
                'quantidade' => ['required', 'numeric', 'min:0'],
                'unidade' => ['nullable', 'string', 'max:20'],
                'custo_unitario' => ['nullable', 'numeric', 'min:0'],
                'notas' => ['nullable', 'string'],
            ]);

            $produto = $this->resolvedor->resolverProduto($dados['produto']);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        $resultado = DB::transaction(function () use ($dados, $produto) {
            $stock = Stock::query()->firstOrCreate(
                ['produto_id' => $produto->id, 'armazem_id' => null],
                [
                    'quantidade' => 0,
                    'unidade_medida' => $produto->unidade_medida ?: 'un',
                    'data_atualizado' => now()->toDateString(),
                ]
            );

            $antes = (float) $stock->quantidade;
            $quantidade = (float) $dados['quantidade'];

            $depois = match ($dados['tipo']) {
                'entrada' => $antes + $quantidade,
                'saida' => max(0, $antes - $quantidade),
                'inventario' => $quantidade,
            };

            $diferenca = round($depois - $antes, 3);

            $stock->update([
                'quantidade' => max(0, $depois),
                'data_atualizado' => now()->toDateString(),
            ]);

            $movimento = MovimentoStock::query()->create([
                'produto_id' => $produto->id,
                'tipo' => $diferenca >= 0 ? 'entrada' : 'saida',
                'quantidade' => abs($diferenca),
                'unidade_medida' => $dados['unidade'] ?? ($stock->unidade_medida ?: 'un'),
                'custo_unitario' => $dados['custo_unitario'] ?? $produto->custo_unitario,
                'referencia' => $dados['tipo'] === 'inventario' ? 'Inventário' : 'Ajuste manual',
                'notas' => $dados['notas'] ?? null,
            ]);

            return compact('stock', 'antes', 'depois', 'diferenca', 'movimento');
        });

        return $this->criado([
            'produto' => ['id' => $produto->id, 'nome' => $produto->nome],
            'antes' => round($resultado['antes'], 3),
            'depois' => round($resultado['depois'], 3),
            'diferenca' => $resultado['diferenca'],
            'unidade' => $resultado['stock']->unidade_medida,
            'movimento_id' => $resultado['movimento']->id,
        ], $resultado['diferenca'] == 0.0 ? ['o stock ja estava neste valor; nada mudou.'] : []);
    }

    // ── Cadastro simples ─────────────────────────────────────────────────────

    public function guardarFuncionario(Request $request, ?Funcionario $funcionario = null): JsonResponse
    {
        return $this->guardar($request, $funcionario ?? new Funcionario, 'funcionario', [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'cargo' => ['nullable', 'string', 'max:100'],
            'aplicador_numero_autorizacao' => ['nullable', 'string', 'max:100'],
            'data_admissao' => ['nullable', 'date'],
            'data_saida' => ['nullable', 'date'],
            'tipo_contrato' => ['nullable', 'string', 'max:50'],
            'valor_hora' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'observacoes' => ['nullable', 'string'],
        ], ['status' => 'ativo']);
    }

    public function guardarEquipa(Request $request, ?Equipa $equipa = null): JsonResponse
    {
        return $this->guardar($request, $equipa ?? new Equipa, 'equipa', [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
        ], ['status' => 'ativa']);
    }

    public function guardarFornecedor(Request $request, ?Fornecedor $fornecedor = null): JsonResponse
    {
        return $this->guardar($request, $fornecedor ?? new Fornecedor, 'fornecedor', [
            'nome' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'observacoes' => ['nullable', 'string'],
        ], ['status' => 'ativo']);
    }

    public function guardarArmazem(Request $request, ?Armazem $armazem = null): JsonResponse
    {
        return $this->guardar($request, $armazem ?? new Armazem, 'armazem', [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'capacidade' => ['nullable', 'numeric', 'min:0'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'observacoes' => ['nullable', 'string'],
        ], ['status' => 'ativo']);
    }

    public function guardarProduto(Request $request, ?Produto $produto = null): JsonResponse
    {
        return $this->guardar($request, $produto ?? new Produto, 'produto', [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'codigo_interno' => ['nullable', 'string', 'max:255'],
            'numero_autorizacao_dgav' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_nome' => ['nullable', 'string', 'max:255'],
            'estabelecimento_venda_autorizacao' => ['nullable', 'string', 'max:255'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'unidade_medida' => ['nullable', 'string', 'max:50'],
            'conteudo' => ['nullable', 'numeric', 'gt:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'descricao' => ['nullable', 'string'],
            'data_validade' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Criar ou alterar, com as mesmas regras nos dois casos.
     *
     * A criar, os campos obrigatorios sao-no; a alterar, so se valida o que
     * vier — mandar so o telefone nao pode obrigar a reenviar o nome.
     *
     * @param  array<string, mixed>  $regras
     * @param  array<string, mixed>  $porOmissao
     */
    private function guardar(Request $request, Model $modelo, string $nome, array $regras, array $porOmissao = []): JsonResponse
    {
        $aCriar = ! $modelo->exists;

        if (! $aCriar) {
            $regras = array_map(
                fn (array $regra) => array_values(array_map(
                    fn ($r) => $r === 'required' ? 'sometimes' : $r,
                    $regra
                )),
                $regras
            );
        }

        try {
            $dados = $request->validate($regras);
        } catch (ValidationException $excepcao) {
            return $this->erro422($excepcao->errors());
        }

        if ($dados === []) {
            return $this->erro422(['corpo' => ['Nao veio nenhum campo para alterar.']]);
        }

        if ($aCriar) {
            $dados = [...$porOmissao, ...$dados];
        }

        $modelo->fill($dados)->save();

        $dadosResposta = [$nome => ['id' => $modelo->id] + $modelo->only(array_keys($regras))];

        return $aCriar ? $this->criado($dadosResposta) : $this->ok($dadosResposta);
    }
}
