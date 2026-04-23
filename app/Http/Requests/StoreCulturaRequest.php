<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCulturaRequest extends FormRequest
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
            'parcela_id' => 'required|exists:parcelas,id',
            'nome' => 'required|string|max:255',
            'grupo_cultura' => 'nullable|in:arvores_fruto,olival,vinha,horticolas,cereais,leguminosas,forragens,aromaticas,florestais,ornamentais,outro',
            'ciclo_produtivo' => 'nullable|in:anual,permanente',
            'ano_inicio_producao' => 'nullable|integer|min:1900|max:' . ((int) date('Y') + 1),
            'data_fim_producao' => 'nullable|date|after_or_equal:data_plantacao',
            'tipo' => 'required|string|max:255',
            'variedade' => 'nullable|string|max:255',
            'data_plantacao' => 'required|date',
            'ciclo_dias' => 'nullable|integer|min:1',
            'previsao_colheita' => 'nullable|date|after:data_plantacao',
            'quantidade_esperada' => 'nullable|numeric|min:0.01',
            'unidade_medida' => 'nullable|string|max:50',
            'estado' => 'in:planejada,em_crescimento,madura,colhida,cancelada',
            'observacoes' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $grupo = $this->input('grupo_cultura') ?: 'outro';

        $this->merge([
            'grupo_cultura' => $grupo,
            'ciclo_produtivo' => $this->input('ciclo_produtivo') ?: $this->defaultCicloProdutivo($grupo),
            'quantidade_esperada' => $this->normalizeDecimal($this->input('quantidade_esperada')),
            'unidade_medida' => $this->input('unidade_medida') ?: 'kg',
        ]);
    }

    private function normalizeDecimal($value)
    {
        return is_string($value) ? str_replace(',', '.', $value) : $value;
    }

    private function defaultCicloProdutivo(string $grupo): string
    {
        return in_array($grupo, ['arvores_fruto', 'olival', 'vinha', 'florestais'], true)
            ? 'permanente'
            : 'anual';
    }

    public function messages(): array
    {
        return [
            'parcela_id.required' => 'A parcela é obrigatória',
            'parcela_id.exists' => 'A parcela selecionada não existe',
            'nome.required' => 'O nome da cultura é obrigatório',
            'tipo.required' => 'O tipo de cultura é obrigatório',
            'data_plantacao.required' => 'A data de plantação é obrigatória',
            'previsao_colheita.after' => 'A previsão de colheita deve ser após a data de plantação',
        ];
    }
}
