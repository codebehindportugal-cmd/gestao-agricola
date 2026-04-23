<?php

namespace App\Http\Requests;

use App\Models\Funcionario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Funcionario|null $funcionario */
        $funcionario = $this->route('funcionario');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('funcionarios', 'email')->ignore($funcionario?->id)],
            'telefone' => ['nullable', 'string', 'max:50'],
            'cargo' => ['required', 'string', 'max:255'],
            'data_admissao' => ['required', 'date'],
            'data_saida' => ['nullable', 'date', 'after_or_equal:data_admissao'],
            'tipo_contrato' => ['required', Rule::in(['permanente', 'temporario', 'estagiario'])],
            'valor_hora' => ['nullable', 'numeric', 'min:0'],
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
            'valor_hora' => $this->input('valor_hora') === '' ? null : $this->input('valor_hora'),
            'status' => $this->input('status') ?: 'ativo',
        ]);
    }
}
