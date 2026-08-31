<?php

namespace App\Http\Requests;

use App\Models\Compromisso;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompromissoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Compromisso::class) ?? false;
    }

    public function rules(): array
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
            'estado' => ['nullable', Rule::in(Compromisso::ESTADOS)],
            'recorrencia' => ['nullable', Rule::in(Compromisso::RECORRENCIAS)],
            'recorrencia_intervalo' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recorrencia_unidade' => ['nullable', Rule::in(Compromisso::UNIDADES_RECORRENCIA)],
            'recorrencia_fim' => ['nullable', 'date', 'after_or_equal:data'],
            'antecedencia_aviso_dias' => ['nullable', 'integer', 'min:0', 'max:365'],
            'campanha_id' => ['nullable', 'exists:campanhas,id'],
            'parcela_id' => ['nullable', 'exists:parcelas,id'],
            'cultura_id' => ['nullable', 'exists:culturas,id'],
            'maquina_id' => ['nullable', 'exists:maquinas,id'],
            'funcionario_id' => ['nullable', 'exists:funcionarios,id'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('recorrencia') !== 'personalizada') {
                return;
            }

            if (! $this->filled('recorrencia_intervalo') || ! $this->filled('recorrencia_unidade')) {
                $validator->errors()->add(
                    'recorrencia_intervalo',
                    'Numa recorrência personalizada indique o intervalo e a unidade (ex.: de 2 em 2 meses).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Dê um nome ao compromisso.',
            'data.required' => 'Indique a data.',
            'categoria.in' => 'Categoria inválida.',
            'recorrencia_fim.after_or_equal' => 'O fim da recorrência não pode ser anterior à primeira data.',
        ];
    }
}
