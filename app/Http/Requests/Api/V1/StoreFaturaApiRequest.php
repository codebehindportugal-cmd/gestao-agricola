<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Controllers\DespesaManagementController;
use App\Models\Custo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreFaturaApiRequest extends FormRequest
{
    public const TAXAS_IVA = [0, 6, 13, 23];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::regrasFatura();
    }

    /**
     * As regras de uma fatura, sem prefixo.
     *
     * Sao publicas e static porque o lote valida cada fatura por si (ver
     * FaturaController::lote): uma fatura mal lida nao pode levar atras as
     * outras do mesmo envio, e um validador com 'faturas.*.' faria cair todas
     * juntas.
     *
     * @return array<string, mixed>
     */
    public static function regrasFatura(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:255'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'categoria' => ['nullable', 'string', Rule::in(DespesaManagementController::CATEGORIAS)],
            'campanha' => ['nullable'],
            'maquina' => ['nullable'],
            'notas' => ['nullable', 'string'],

            'criar_produtos' => ['nullable', 'boolean'],
            'actualizar_custo_unitario' => ['nullable', 'boolean'],
            'dar_entrada_em_stock' => ['nullable', 'boolean'],
            'criar_custo' => ['nullable', 'boolean'],
            // Custo partilhado por varias campanhas. Por omissao, uma fatura
            // sem campanha nem maquina nasce rateavel; isto serve para forcar
            // o contrario, ou para escolher a base do rateio.
            'rateavel' => ['nullable', 'boolean'],
            'base_rateio' => ['nullable', 'string', Rule::in(Custo::BASES_RATEIO)],

            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.produto' => ['nullable'],
            'linhas.*.codigo' => ['nullable', 'string', 'max:255'],
            'linhas.*.descricao' => ['required', 'string', 'max:255'],
            'linhas.*.quantidade' => ['required', 'numeric', 'gt:0'],
            // Quanto leva cada embalagem desta linha (2 bidoes de 5 L sao 10 L
            // de stock). Ganha ao tamanho lido da designacao e ao do catalogo.
            'linhas.*.conteudo_embalagem' => ['nullable', 'numeric', 'gt:0'],
            'linhas.*.unidade_embalagem' => ['nullable', 'string', 'max:20'],
            'linhas.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'linhas.*.desconto_percentagem' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.iva_percentagem' => ['nullable', 'numeric', Rule::in(self::TAXAS_IVA)],
            'linhas.*.tipo_produto' => ['nullable', 'string', 'max:255'],
            'linhas.*.numero_autorizacao_dgav' => ['nullable', 'string', 'max:255'],
            'linhas.*.unidade_medida' => ['nullable', 'string', 'max:50'],
            'linhas.*.estabelecimento_venda_nome' => ['nullable', 'string', 'max:255'],
            'linhas.*.estabelecimento_venda_autorizacao' => ['nullable', 'string', 'max:255'],
            'linhas.*.notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return self::mensagensFatura();
    }

    /** @return array<string, string> */
    public static function mensagensFatura(): array
    {
        $taxas = implode(', ', self::TAXAS_IVA);
        $categorias = implode(', ', DespesaManagementController::CATEGORIAS);

        return [
            'linhas.*.iva_percentagem.in' => "Taxa de IVA invalida. Valores aceites: {$taxas}.",
            'categoria.in' => "Categoria invalida. Valores aceites: {$categorias}.",
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'sucesso' => false,
            'dados' => null,
            'avisos' => [],
            'erros' => $validator->errors()->toArray(),
        ], 422));
    }
}
