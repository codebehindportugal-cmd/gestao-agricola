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

            // Uma parcela (formato antigo) ou varias parcelas numa so chamada.
            'parcela' => ['required_without:parcelas'],
            'parcelas' => ['required_without:parcela', 'array', 'min:1'],
            'parcelas.*' => ['required'],
            'parcelas.*.parcela' => ['sometimes', 'required'],
            'parcelas.*.cultura' => ['sometimes', 'nullable'],
            'parcelas.*.area_tratada' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parcelas.*.volume_calda' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parcelas.*.observacoes' => ['sometimes', 'nullable', 'string'],
            'parcelas.*.produtos' => ['sometimes', 'array', 'min:1'],
            'parcelas.*.duracao_horas' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parcelas.*.combustivel_gasto_l' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'cultura' => ['nullable'],
            'data' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data'],
            'tipo' => ['nullable', 'string', 'max:255'],

            // Meios utilizados
            'maquina' => ['nullable'],
            'alfaia' => ['nullable'],
            'funcionario' => ['nullable'],
            'equipa' => ['nullable'],
            'duracao_horas' => ['nullable', 'numeric', 'min:0'],
            'distancia_km' => ['nullable', 'numeric', 'min:0'],
            'combustivel_gasto_l' => ['nullable', 'numeric', 'min:0'],

            // Caderno de campo / DGAV
            'produtor_nome' => ['nullable', 'string', 'max:255'],
            'aplicador_nome' => ['nullable', 'string', 'max:255'],
            'aplicador_numero_autorizacao' => ['nullable', 'string', 'max:255'],
            'exploracao_concelho' => ['nullable', 'string', 'max:255'],
            'exploracao_freguesia' => ['nullable', 'string', 'max:255'],

            'custo_estimado' => ['nullable', 'numeric', 'min:0'],
            'custo_real' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],

            'produtos' => ['required_without:parcelas', 'array', 'min:1'],
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

    public function messages(): array
    {
        return [
            'produtos.required_without' => 'The produtos field is required.',
            'parcela.required_without' => 'The parcela field is required.',
            'parcelas.required_without' => 'The parcelas field is required.',
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
