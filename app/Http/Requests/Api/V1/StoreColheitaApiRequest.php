<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreColheitaApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campanha' => ['required'],
            'cultura' => ['required'],
            'parcela' => ['nullable'],
            'data' => ['required', 'date'],
            'quantidade_total' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['nullable', 'string', 'max:50'],
            'unidade_medida' => ['nullable', 'string', 'max:50'],
            'qualidade' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'lotes' => ['required', 'array', 'min:1'],
            'lotes.*.codigo' => ['nullable', 'string', 'max:255'],
            'lotes.*.terreno' => ['required'],
            'lotes.*.data_colheita' => ['nullable', 'date'],
            'lotes.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'lotes.*.unidade' => ['required', 'string', 'max:50'],
            'lotes.*.localizacao_armazem' => ['nullable', 'string', 'max:255'],
            'lotes.*.observacoes' => ['nullable', 'string'],
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
