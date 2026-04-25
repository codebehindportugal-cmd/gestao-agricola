<?php

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Operacao;
use App\Models\Parcela;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Operacao::query()
            ->whereNull('campanha_id')
            ->whereNotNull('parcela_id')
            ->get()
            ->each(function (Operacao $operacao) {
                $culturaId = $operacao->cultura_id
                    ?: $this->culturaIdForParcela((int) $operacao->parcela_id, $operacao->data_hora_inicio);

                if (! $culturaId) {
                    return;
                }

                $operacao->forceFill([
                    'cultura_id' => $culturaId,
                    'campanha_id' => $this->campanhaIdFor($culturaId, Carbon::parse($operacao->data_hora_inicio ?? now())),
                ])->save();
            });

        Colheita::query()
            ->whereNull('campanha_id')
            ->whereNotNull('parcela_id')
            ->get()
            ->each(function (Colheita $colheita) {
                $culturaId = $colheita->cultura_id
                    ?: $this->culturaIdForParcela((int) $colheita->parcela_id, $colheita->data_colheita);

                if (! $culturaId) {
                    return;
                }

                $colheita->forceFill([
                    'cultura_id' => $culturaId,
                    'campanha_id' => $this->campanhaIdFor($culturaId, Carbon::parse($colheita->data_colheita ?? now())),
                ])->save();
            });
    }

    public function down(): void
    {
        //
    }

    private function culturaIdForParcela(int $parcelaId, mixed $date = null): ?int
    {
        $cultureId = Cultura::query()
            ->where('parcela_id', $parcelaId)
            ->orderBy('id')
            ->value('id');

        if ($cultureId) {
            return (int) $cultureId;
        }

        $parcela = Parcela::query()->find($parcelaId, ['id', 'nome', 'tipo_ocupacao']);

        if (! $parcela) {
            return null;
        }

        return (int) Cultura::query()->create([
            'parcela_id' => $parcela->id,
            'nome' => $parcela->nome,
            'tipo' => $parcela->tipo_ocupacao ?: 'outro',
            'data_plantacao' => Carbon::parse($date ?? now())->toDateString(),
            'estado' => 'em_crescimento',
        ])->id;
    }

    private function campanhaIdFor(int $culturaId, Carbon $date): int
    {
        $cultura = Cultura::query()->find($culturaId, ['id', 'quantidade_esperada']);

        return Campanha::query()->firstOrCreate(
            [
                'cultura_id' => $culturaId,
                'ano' => (int) $date->year,
            ],
            [
                'data_inicio' => $date->copy()->startOfYear()->toDateString(),
                'status' => 'em_curso',
                'producao_esperada' => $cultura?->quantidade_esperada,
            ]
        )->id;
    }
};
