<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlfaiaRequest extends FormRequest
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
            'nome' => 'string|max:255',
            'tipo' => 'string|max:255',
            'maquina_id' => 'nullable|exists:maquinas,id',
            'descricao' => 'nullable|string',
            'comprimento' => 'nullable|numeric|min:0.01',
            'largura' => 'nullable|numeric|min:0.01',
            'estado' => 'in:operacional,danificada,retirada',
            'observacoes' => 'nullable|string',
        ];
    }
}
