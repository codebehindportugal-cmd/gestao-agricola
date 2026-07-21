<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAplicacaoApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campanha' => ['required'],
            'parcela' => ['required'],
            'cultura' => ['nullable'],
            'data' => ['required', 'date'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'produtor_nome' => ['nullable', 'string', 'max:255'],
            'aplicador_nome' => ['nullable', 'string', 'max:255'],
            'aplicador_numero_autorizacao' => ['nullable', 'string', 'max:255'],
            'exploracao_concelho' => ['nullable', 'string', 'max:255'],
            'exploracao_freguesia' => ['nullable', 'string', 'max:255'],
            'custo_estimado' => ['nullable', 'numeric', 'min:0'],
            'custo_real' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'produtos' => ['required', 'array', 'min:1'],
            'produtos.*.produto' => ['required'],
            'produtos.*.quantidade' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.dose' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.dose_unidade' => ['nullable', 'string', 'max:50'],
            'produtos.*.area_tratada' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.volume_calda' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.finalidade' => ['nullable', 'string', 'max:255'],
            'produtos.*.intervalo_seguranca_dias' => ['nullable', 'integer', 'min:0'],
            'produtos.*.estabelecimento_venda_nome' => ['nullable', 'string', 'max:255'],
            'produtos.*.estabelecimento_venda_autorizacao' => ['nullable', 'string', 'max:255'],
            'produtos.*.custo_unitario' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.observacoes' => ['nullable', 'string'],
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
