<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaquinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'string|max:255',
            'tipo' => 'string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'matricula' => 'nullable|string|max:255|unique:maquinas,matricula,' . $this->maquina->id,
            'numero_serie' => 'nullable|string|max:255|unique:maquinas,numero_serie,' . $this->maquina->id,
            'ano_aquisicao' => 'nullable|integer|min:1901|max:' . date('Y'),
            'horas_uso' => 'nullable|numeric|min:0',
            'horas_manutencao' => 'nullable|numeric|min:0',
            'consumo_agua_ha' => 'nullable|numeric|min:0',
            'consumo_combustivel' => 'nullable|numeric|min:0',
            'estado' => 'in:operacional,em_manutencao,danificada,retirada',
            'observacoes' => 'nullable|string',
        ];
    }
}
