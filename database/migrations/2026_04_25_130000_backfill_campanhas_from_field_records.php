<?php

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Operacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Operacao::query()
            ->whereNotNull('cultura_id')
            ->whereNull('campanha_id')
            ->get()
            ->each(function (Operacao $operacao) {
                $campanhaId = $this->campanhaIdFor(
                    (int) $operacao->cultura_id,
                    Carbon::parse($operacao->data_hora_inicio ?? now())
                );

                $operacao->forceFill(['campanha_id' => $campanhaId])->save();
            });

        Colheita::query()
            ->whereNotNull('cultura_id')
            ->whereNull('campanha_id')
            ->get()
            ->each(function (Colheita $colheita) {
                $campanhaId = $this->campanhaIdFor(
                    (int) $colheita->cultura_id,
                    Carbon::parse($colheita->data_colheita ?? now())
                );

                $colheita->forceFill(['campanha_id' => $campanhaId])->save();
            });
    }

    public function down(): void
    {
        //
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
