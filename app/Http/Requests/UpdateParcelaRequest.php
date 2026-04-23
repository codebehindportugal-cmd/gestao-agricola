<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParcelaRequest extends FormRequest
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
            'terreno_id' => 'exists:terrenos,id',
            'nome' => 'string|max:255',
            'numero_parcela' => 'nullable|string|max:255',
            'area_total' => 'numeric|min:0.01',
            'area_util' => 'nullable|numeric|min:0.01',
            'descricao' => 'nullable|string',
            'estado' => 'in:livre,cultivada,em_preparacao,pousio',
            'tipo_ocupacao' => 'in:culturas_anuais,pomar,misto,estufa,outro',
            'numero_arvores' => 'nullable|integer|min:0',
            'compasso_linha_m' => 'nullable|numeric|min:0.01',
            'compasso_planta_m' => 'nullable|numeric|min:0.01',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'poligono' => 'nullable|array|min:3',
            'poligono.*' => 'array|size:2',
            'poligono.*.0' => 'numeric|between:-90,90',
            'poligono.*.1' => 'numeric|between:-180,180',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'area_total' => $this->normalizeDecimal($this->input('area_total')),
            'area_util' => $this->normalizeDecimal($this->input('area_util')),
            'compasso_linha_m' => $this->normalizeDecimal($this->input('compasso_linha_m')),
            'compasso_planta_m' => $this->normalizeDecimal($this->input('compasso_planta_m')),
            'poligono' => $this->filledPolygon(),
        ]);
    }

    private function normalizeDecimal($value)
    {
        return is_string($value) ? str_replace(',', '.', $value) : $value;
    }

    private function filledPolygon(): ?array
    {
        $polygon = $this->input('poligono');

        return is_array($polygon) && count($polygon) ? $polygon : null;
    }
}
