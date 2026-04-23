<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTerrenoRequest extends FormRequest
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
            'descricao' => 'nullable|string',
            'area_total' => 'required|numeric|min:0.01',
            'tipo_solo' => 'nullable|string|max:255',
            'localizacao' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'poligono' => 'nullable|array|min:3',
            'poligono.*' => 'array|size:2',
            'poligono.*.0' => 'numeric|between:-90,90',
            'poligono.*.1' => 'numeric|between:-180,180',
            'estado' => 'in:ativo,inativo,em_manutencao',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'area_total' => $this->normalizeDecimal($this->input('area_total')),
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

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do terreno é obrigatório',
            'area_total.required' => 'A área total é obrigatória',
            'area_total.numeric' => 'A área deve ser um número',
            'latitude.between' => 'A latitude deve estar entre -90 e 90',
            'longitude.between' => 'A longitude deve estar entre -180 e 180',
        ];
    }
}
