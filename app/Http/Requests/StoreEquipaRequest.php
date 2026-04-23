<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'lider_id' => ['nullable', 'exists:funcionarios,id'],
            'descricao' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['ativa', 'inativa'])],
            'funcionario_ids' => ['nullable', 'array'],
            'funcionario_ids.*' => ['integer', 'exists:funcionarios,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'lider_id' => $this->input('lider_id') ?: null,
            'status' => $this->input('status') ?: 'ativa',
            'funcionario_ids' => array_values(array_filter((array) $this->input('funcionario_ids', []))),
        ]);
    }
}
