<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreReceitaApiRequest extends FormRequest
{
    public const TIPOS = [
        'venda_colheita',
        'subsidio',
        'servico',
        'outro',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receitas' => ['sometimes', 'array', 'min:1'],
            'receitas.*' => ['required', 'array'],
            '*.descricao' => ['exclude'],

            'descricao' => ['required_without:receitas', 'string', 'max:255'],
            'tipo' => ['required_without:receitas', 'string', Rule::in(self::TIPOS)],
            'valor' => ['required_without:receitas', 'numeric', 'gt:0'],
            'data' => ['required_without:receitas', 'date'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'comprador_nome' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'campanha' => ['nullable'],
            'cultura' => ['nullable'],
            'parcela' => ['nullable'],
            'colheita' => ['nullable'],
            'lote' => ['nullable'],

            'receitas.*.descricao' => ['required_with:receitas', 'string', 'max:255'],
            'receitas.*.tipo' => ['required_with:receitas', 'string', Rule::in(self::TIPOS)],
            'receitas.*.valor' => ['required_with:receitas', 'numeric', 'gt:0'],
            'receitas.*.data' => ['required_with:receitas', 'date'],
            'receitas.*.referencia_externa' => ['nullable', 'string', 'max:255'],
            'receitas.*.comprador_nome' => ['nullable', 'string', 'max:255'],
            'receitas.*.documento' => ['nullable', 'string', 'max:255'],
            'receitas.*.observacoes' => ['nullable', 'string'],
            'receitas.*.campanha' => ['nullable'],
            'receitas.*.cultura' => ['nullable'],
            'receitas.*.parcela' => ['nullable'],
            'receitas.*.colheita' => ['nullable'],
            'receitas.*.lote' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        $tipos = implode(', ', self::TIPOS);

        return [
            'tipo.in' => "Tipo invalido. Valores aceites: {$tipos}.",
            'receitas.*.tipo.in' => "Tipo invalido. Valores aceites: {$tipos}.",
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
