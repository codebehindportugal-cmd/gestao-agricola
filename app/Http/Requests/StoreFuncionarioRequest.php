<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:funcionarios,email'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'cargo' => ['required', 'string', 'max:255'],
            'data_admissao' => ['required', 'date'],
            'data_saida' => ['nullable', 'date', 'after_or_equal:data_admissao'],
            'tipo_contrato' => ['required', Rule::in(['permanente', 'temporario', 'estagiario'])],
            'status' => ['required', Rule::in(['ativo', 'inativo', 'em_licenca'])],
            'observacoes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->input('email') ?: null,
            'telefone' => $this->input('telefone') ?: null,
            'data_saida' => $this->input('data_saida') ?: null,
            'tipo_contrato' => $this->input('tipo_contrato') ?: 'permanente',
            'status' => $this->input('status') ?: 'ativo',
        ]);
    }
}
