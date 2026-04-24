<?php

namespace App\Http\Requests;

use App\Models\Produto;
use App\Models\Cultura;
use App\Models\Campanha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOperacaoRequest extends FormRequest
{
    private const TIPOS = [
        'mobilização do solo',
        'sementeira',
        'plantação',
        'rega',
        'fertilização',
        'tratamento fitossanitário',
        'poda',
        'limpeza',
        'manutenção',
        'colheita',
        'transporte',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parcela_id' => 'required_without:parcela_ids|nullable|exists:parcelas,id',
            'parcela_ids' => 'nullable|array|min:1',
            'parcela_ids.*' => 'required|exists:parcelas,id|distinct',
            'cultura_id' => 'nullable|exists:culturas,id',
            'campanha_id' => 'nullable|exists:campanhas,id',
            'tipo' => ['required', 'string', 'max:255', Rule::in(self::TIPOS)],
            'data_hora_inicio' => 'required|date',
            'data_hora_fim' => 'nullable|date|after:data_hora_inicio',
            'maquina_id' => 'nullable|exists:maquinas,id',
            'alfaia_id' => 'nullable|exists:alfaias,id',
            'operador_id' => 'nullable|exists:users,id',
            'funcionario_id' => 'nullable|exists:funcionarios,id',
            'equipa_id' => 'nullable|exists:equipas,id',
            'produtor_nome' => 'nullable|string|max:255',
            'aplicador_nome' => 'nullable|string|max:255',
            'aplicador_numero_autorizacao' => 'nullable|string|max:255',
            'exploracao_concelho' => 'nullable|string|max:255',
            'exploracao_freguesia' => 'nullable|string|max:255',
            'duracao_horas' => 'nullable|numeric|min:0',
            'custo_estimado' => 'nullable|numeric|min:0',
            'custo_real' => 'nullable|numeric|min:0',
            'estado' => 'required|in:planejada,em_curso,concluida,cancelada',
            'observacoes' => 'nullable|string',
            'produtos' => 'nullable|array',
            'produtos.*.produto_id' => 'required_with:produtos|exists:produtos,id|distinct',
            'produtos.*.quantidade' => 'required_with:produtos|numeric|min:0.01',
            'produtos.*.unidade_medida' => 'required_with:produtos|string|max:50',
            'produtos.*.dose' => 'nullable|numeric|min:0',
            'produtos.*.dose_unidade' => 'nullable|string|max:50',
            'produtos.*.area_tratada' => 'nullable|numeric|min:0',
            'produtos.*.volume_calda' => 'nullable|numeric|min:0',
            'produtos.*.finalidade' => 'nullable|string|max:255',
            'produtos.*.intervalo_seguranca_dias' => 'nullable|integer|min:0',
            'produtos.*.estabelecimento_venda_nome' => 'nullable|string|max:255',
            'produtos.*.estabelecimento_venda_autorizacao' => 'nullable|string|max:255',
            'produtos.*.custo_unitario' => 'nullable|numeric|min:0',
            'produtos.*.observacoes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipo = $this->input('tipo');
            $produtos = collect($this->input('produtos', []))->filter(fn ($produto) => !empty($produto['produto_id']));

            if ($tipo === 'tratamento fitossanitário' && $produtos->isEmpty()) {
                $validator->errors()->add('produtos', 'Indique pelo menos um produto fitofarmacêutico para o tratamento fitossanitário.');
            }

            if ($tipo === 'tratamento fitossanitário' && $produtos->isNotEmpty()) {
                $validProductIds = Produto::query()
                    ->whereIn('id', $produtos->pluck('produto_id')->filter())
                    ->get(['id', 'tipo'])
                    ->filter(fn (Produto $produto) => in_array($this->normaliseText($produto->tipo), [
                        'fitofarmaco',
                        'fitofarmaceutico',
                        'produto fitofarmaceutico',
                    ], true))
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);

                $hasInvalidProduct = $produtos
                    ->pluck('produto_id')
                    ->map(fn ($id) => (string) $id)
                    ->diff($validProductIds)
                    ->isNotEmpty();

                if ($hasInvalidProduct) {
                    $validator->errors()->add('produtos', 'No tratamento fitossanitário só pode usar produtos fitofarmacêuticos.');
                }
            }

            $selectedParcelIds = collect($this->input('parcela_ids', []))
                ->filter()
                ->map(fn ($id) => (string) $id);

            if ($selectedParcelIds->isEmpty() && $this->filled('parcela_id')) {
                $selectedParcelIds->push((string) $this->input('parcela_id'));
            }

            if ($selectedParcelIds->count() > 1 && ($this->filled('cultura_id') || $this->filled('campanha_id'))) {
                $validator->errors()->add('parcela_ids', 'Ao registar em varias parcelas, escolha a cultura e campanha depois em cada operacao, se necessario.');
            }

            if ($this->filled('cultura_id') && $selectedParcelIds->count() === 1) {
                $cultureMatchesParcel = Cultura::query()
                    ->where('id', $this->input('cultura_id'))
                    ->where('parcela_id', $selectedParcelIds->first())
                    ->exists();

                if (! $cultureMatchesParcel) {
                    $validator->errors()->add('cultura_id', 'A cultura selecionada não pertence à parcela indicada.');
                }
            }

            if ($this->filled('campanha_id') && $this->filled('cultura_id')) {
                $campaignMatchesCulture = Campanha::query()
                    ->where('id', $this->input('campanha_id'))
                    ->where('cultura_id', $this->input('cultura_id'))
                    ->exists();

                if (! $campaignMatchesCulture) {
                    $validator->errors()->add('campanha_id', 'A campanha selecionada não pertence à cultura indicada.');
                }
            }

            if ($tipo === 'tratamento fitossanitário') {
                foreach (['aplicador_nome', 'aplicador_numero_autorizacao', 'exploracao_concelho', 'exploracao_freguesia'] as $field) {
                    if (blank($this->input($field))) {
                        $validator->errors()->add($field, 'Este campo é obrigatório para o registo DGAV da aplicação fitofarmacêutica.');
                    }
                }

                $produtos->each(function ($produto, $index) use ($validator) {
                    foreach (['dose', 'dose_unidade', 'area_tratada', 'volume_calda', 'finalidade', 'intervalo_seguranca_dias'] as $field) {
                        if (blank($produto[$field] ?? null)) {
                            $validator->errors()->add("produtos.{$index}.{$field}", 'Este campo é obrigatório para o caderno de campo fitofarmacêutico.');
                        }
                    }
                });
            }
        });
    }

    private function normaliseText(?string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value ?? '') ?: '';

        return strtolower($value);
    }

    public function messages(): array
    {
        return [
            'parcela_id.required' => 'A parcela é obrigatória.',
            'parcela_id.required_without' => 'A parcela é obrigatória.',
            'parcela_id.exists' => 'A parcela selecionada não existe.',
            'parcela_ids.min' => 'Selecione pelo menos uma parcela.',
            'parcela_ids.*.exists' => 'Uma das parcelas selecionadas não existe.',
            'parcela_ids.*.distinct' => 'A mesma parcela não pode ser repetida.',
            'tipo.required' => 'O tipo de operação é obrigatório.',
            'tipo.in' => 'Selecione um tipo de operação válido.',
            'data_hora_inicio.required' => 'A data e hora de início são obrigatórias.',
            'data_hora_inicio.date' => 'A data e hora de início devem ser válidas.',
            'data_hora_fim.after' => 'A data/hora de fim deve ser posterior ao início.',
            'estado.required' => 'O estado da operação é obrigatório.',
            'estado.in' => 'Selecione um estado válido.',
            'funcionario_id.exists' => 'O trabalhador selecionado não existe.',
            'equipa_id.exists' => 'A equipa selecionada não existe.',
            'campanha_id.exists' => 'A campanha selecionada não existe.',
            'duracao_horas.min' => 'A duração não pode ser negativa.',
            'custo_estimado.min' => 'O custo estimado não pode ser negativo.',
            'custo_real.min' => 'O custo real não pode ser negativo.',
            'produtos.*.produto_id.required_with' => 'Selecione o produto.',
            'produtos.*.produto_id.exists' => 'Um dos produtos selecionados não existe.',
            'produtos.*.produto_id.distinct' => 'O mesmo produto não pode ser repetido na operação.',
            'produtos.*.quantidade.required_with' => 'Indique a quantidade do produto.',
            'produtos.*.quantidade.numeric' => 'A quantidade deve ser numérica.',
            'produtos.*.quantidade.min' => 'A quantidade deve ser superior a zero.',
            'produtos.*.unidade_medida.required_with' => 'Indique a unidade de medida do produto.',
            'produtos.*.dose.numeric' => 'A dose deve ser numérica.',
            'produtos.*.area_tratada.numeric' => 'A área tratada deve ser numérica.',
            'produtos.*.volume_calda.numeric' => 'O volume de calda deve ser numérico.',
            'produtos.*.intervalo_seguranca_dias.integer' => 'O intervalo de segurança deve ser indicado em dias.',
            'produtos.*.estabelecimento_venda_nome.string' => 'Indique o estabelecimento de venda do produto.',
            'produtos.*.estabelecimento_venda_autorizacao.string' => 'Indique o número de autorização do estabelecimento.',
        ];
    }
}
