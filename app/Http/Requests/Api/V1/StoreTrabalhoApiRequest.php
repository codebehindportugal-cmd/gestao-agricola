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
            // Uma apanha pode ter dado varias colheitas, uma por pomar; o custo
            // reparte-se pelos quilos de cada uma.
            'colheita' => ['nullable'],
            'colheitas' => ['nullable', 'array', 'max:100'],
            'colheitas.*' => ['required'],

            // Recurso principal, mantido por compatibilidade: equivale a uma
            // linha de maquinas[].
            'maquina' => ['nullable'],
            'alfaia' => ['nullable'],

            // Todas as maquinas, alfaias e viaturas da operacao. Uma apanha
            // pode levar dois tratores, dois empilhadores e um carro.
            'maquinas' => ['nullable', 'array', 'max:50'],
            'maquinas.*.maquina' => ['nullable'],
            'maquinas.*.alfaia' => ['nullable'],
            'maquinas.*.nome' => ['nullable', 'string', 'max:255'],
            'maquinas.*.papel' => ['nullable', 'string', 'max:255'],
            'maquinas.*.unidades' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maquinas.*.horas' => ['nullable', 'numeric', 'min:0'],
            'maquinas.*.horas_por_dia' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'maquinas.*.dias' => ['nullable', 'integer', 'min:1', 'max:366'],
            'maquinas.*.km' => ['nullable', 'numeric', 'min:0'],
            'maquinas.*.custo_hora' => ['nullable', 'numeric', 'min:0'],
            'maquinas.*.custo_km' => ['nullable', 'numeric', 'min:0'],
            'maquinas.*.custo_total' => ['nullable', 'numeric', 'min:0'],
            'maquinas.*.observacoes' => ['nullable', 'string'],

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
