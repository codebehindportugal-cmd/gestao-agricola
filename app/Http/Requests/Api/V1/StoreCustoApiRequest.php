<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreCustoApiRequest extends FormRequest
{
    public const TIPOS = [
        'material',
        'mao_obra',
        'maquinaria',
        'energia',
        'manutencao',
        'outro',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'custos' => ['sometimes', 'array', 'min:1'],
            'custos.*' => ['required', 'array'],
            '*.descricao' => ['exclude'],

            'descricao' => ['required_without:custos', 'string', 'max:255'],
            'tipo' => ['required_without:custos', 'string', Rule::in(self::TIPOS)],
            'valor' => ['required_without:custos', 'numeric', 'gt:0'],
            'data' => ['required_without:custos', 'date'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'campanha' => ['nullable'],
            'operacao' => ['nullable'],
            'cultura' => ['nullable'],
            'parcela' => ['nullable'],
            'maquina' => ['nullable'],
            'funcionario' => ['nullable'],

            'custos.*.descricao' => ['required_with:custos', 'string', 'max:255'],
            'custos.*.tipo' => ['required_with:custos', 'string', Rule::in(self::TIPOS)],
            'custos.*.valor' => ['required_with:custos', 'numeric', 'gt:0'],
            'custos.*.data' => ['required_with:custos', 'date'],
            'custos.*.referencia_externa' => ['nullable', 'string', 'max:255'],
            'custos.*.observacoes' => ['nullable', 'string'],
            'custos.*.campanha' => ['nullable'],
            'custos.*.operacao' => ['nullable'],
            'custos.*.cultura' => ['nullable'],
            'custos.*.parcela' => ['nullable'],
            'custos.*.maquina' => ['nullable'],
            'custos.*.funcionario' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        $tipos = implode(', ', self::TIPOS);

        return [
            'tipo.in' => "Tipo invalido. Valores aceites: {$tipos}.",
            'custos.*.tipo.in' => "Tipo invalido. Valores aceites: {$tipos}.",
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
