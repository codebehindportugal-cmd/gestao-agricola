<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTrabalhoApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tarefa' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],

            'campanha' => ['nullable'],
            'parcela' => ['nullable'],
            'cultura' => ['nullable'],
            'maquina' => ['nullable'],
            'alfaia' => ['nullable'],

            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:366'],
            'semanas' => ['nullable', 'numeric', 'min:0.1', 'max:52'],
            'incluir_fins_de_semana' => ['nullable', 'boolean'],
            'horas_por_dia' => ['required', 'numeric', 'min:0.25', 'max:24'],

            'funcionarios' => ['nullable', 'array'],
            'funcionarios.*' => ['required'],
            'equipa' => ['nullable'],
            'numero_pessoas' => ['nullable', 'integer', 'min:1', 'max:500'],

            'valor_hora' => ['nullable', 'numeric', 'min:0'],
            'custo_total' => ['nullable', 'numeric', 'min:0'],

            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('data_fim') && ! $this->filled('dias') && ! $this->filled('semanas')) {
                $validator->errors()->add(
                    'data_fim',
                    'Indique data_fim, dias ou semanas para delimitar o periodo de trabalho.'
                );
            }

            if (! $this->filled('funcionarios') && ! $this->filled('equipa') && ! $this->filled('numero_pessoas')) {
                $validator->errors()->add(
                    'funcionarios',
                    'Indique funcionarios, equipa ou numero_pessoas.'
                );
            }
        });
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
