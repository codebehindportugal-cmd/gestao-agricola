<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Compromisso;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreCompromissoApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'compromissos' => ['nullable', 'array', 'min:1'],

            'titulo' => ['required_without:compromissos', 'string', 'max:255'],
            'categoria' => ['required_without:compromissos', Rule::in(Compromisso::CATEGORIAS)],
            'data' => ['required_without:compromissos', 'date'],

            'compromissos.*.titulo' => ['required_with:compromissos', 'string', 'max:255'],
            'compromissos.*.categoria' => ['required_with:compromissos', Rule::in(Compromisso::CATEGORIAS)],
            'compromissos.*.data' => ['required_with:compromissos', 'date'],
        ];
    }

    /**
     * Regras aplicadas a cada compromisso, seja ele o payload de topo ou um
     * item do lote.
     */
    public static function regrasItem(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'categoria' => ['required', Rule::in(Compromisso::CATEGORIAS)],
            'tipo' => ['nullable', 'string', 'max:255'],
            'entidade' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'recorrencia' => ['nullable', Rule::in(Compromisso::RECORRENCIAS)],
            'recorrencia_intervalo' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recorrencia_unidade' => ['nullable', Rule::in(Compromisso::UNIDADES_RECORRENCIA)],
            'recorrencia_fim' => ['nullable', 'date', 'after_or_equal:data'],
            'antecedencia_aviso_dias' => ['nullable', 'integer', 'min:0', 'max:365'],
            'campanha' => ['nullable'],
            'parcela' => ['nullable'],
            'cultura' => ['nullable'],
            'maquina' => ['nullable'],
            'funcionario' => ['nullable'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string'],
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
