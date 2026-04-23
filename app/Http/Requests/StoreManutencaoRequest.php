<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maquina_id' => 'required|exists:maquinas,id',
            'data_manutencao' => 'required|date',
            'tipo' => 'required|string|max:255',
            'descricao' => 'required|string',
            'custo' => 'nullable|numeric|min:0',
            'duracao_minutos' => 'nullable|integer|min:1',
            'proxima_manutencao' => 'nullable|date|after_or_equal:data_manutencao',
            'observacoes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'maquina_id.required' => 'A máquina é obrigatória.',
            'maquina_id.exists' => 'A máquina selecionada não existe.',
            'data_manutencao.required' => 'A data da revisão é obrigatória.',
            'data_manutencao.date' => 'A data da revisão deve ser uma data válida.',
            'tipo.required' => 'O tipo de revisão é obrigatório.',
            'descricao.required' => 'A descrição da revisão é obrigatória.',
            'custo.numeric' => 'O custo deve ser um valor numérico.',
            'custo.min' => 'O custo não pode ser negativo.',
            'duracao_minutos.integer' => 'A duração deve ser indicada em minutos.',
            'duracao_minutos.min' => 'A duração deve ser pelo menos 1 minuto.',
            'proxima_manutencao.date' => 'A próxima revisão deve ser uma data válida.',
            'proxima_manutencao.after_or_equal' => 'A próxima revisão não pode ser anterior à revisão atual.',
        ];
    }
}
