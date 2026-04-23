<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaquinaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'matricula' => 'nullable|string|max:255|unique:maquinas',
            'numero_serie' => 'nullable|string|max:255|unique:maquinas',
            'ano_aquisicao' => 'nullable|integer|min:1901|max:' . date('Y'),
            'horas_uso' => 'nullable|numeric|min:0',
            'horas_manutencao' => 'nullable|numeric|min:0',
            'estado' => 'in:operacional,em_manutencao,danificada,retirada',
            'observacoes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da máquina é obrigatório',
            'tipo.required' => 'O tipo de máquina é obrigatório',
            'matricula.unique' => 'Esta matrícula já existe',
            'numero_serie.unique' => 'Este número de série já existe',
            'ano_aquisicao.min' => 'O ano de aquisição deve ser 1901 ou posterior',
        ];
    }
}
