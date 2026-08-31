<?php

namespace App\Services;

use App\Models\Compromisso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Materializa as ocorrencias futuras das series recorrentes.
 *
 * A serie-mae e ela propria a primeira ocorrencia; as seguintes sao filhas
 * (compromisso_pai_id) com recorrencia 'nenhuma'. Gerar e idempotente: uma data
 * que ja exista na serie nunca e duplicada.
 */
class GeradorCompromissos
{
    public const HORIZONTE_MESES = 18;

    /** Teto de seguranca por serie, para uma recorrencia diaria nao explodir. */
    private const MAX_POR_SERIE = 400;

    /**
     * @return array{series: int, criados: int}
     */
    public function gerarTodas(?int $horizonteMeses = null): array
    {
        $series = Compromisso::query()->series()->get();
        $criados = 0;

        foreach ($series as $serie) {
            $criados += count($this->gerar($serie, $horizonteMeses));
        }

        return ['series' => $series->count(), 'criados' => $criados];
    }

    /**
     * @return array<int, Compromisso>
     */
    public function gerar(Compromisso $serie, ?int $horizonteMeses = null): array
    {
        $passo = $serie->passoRecorrencia();

        if ($passo === null || $serie->data === null) {
            return [];
        }

        [$intervalo, $unidade] = $passo;
        $limite = CarbonImmutable::now()->addMonths($horizonteMeses ?? self::HORIZONTE_MESES)->endOfDay();

        if ($serie->recorrencia_fim !== null) {
            $fimSerie = CarbonImmutable::parse($serie->recorrencia_fim)->endOfDay();
            $limite = $fimSerie->lessThan($limite) ? $fimSerie : $limite;
        }

        $existentes = Compromisso::query()
            ->withTrashed()
            ->where(fn ($q) => $q->where('compromisso_pai_id', $serie->id)->orWhere('id', $serie->id))
            ->pluck('data')
            ->map(fn ($data) => CarbonImmutable::parse($data)->toDateString())
            ->flip();

        $criados = [];
        $data = CarbonImmutable::parse($serie->data);

        for ($i = 0; $i < self::MAX_POR_SERIE; $i++) {
            $data = $this->avancar($data, $intervalo, $unidade);

            if ($data->greaterThan($limite)) {
                break;
            }

            if ($existentes->has($data->toDateString())) {
                continue;
            }

            $criados[] = $this->criarOcorrencia($serie, $data);
        }

        return $criados;
    }

    private function avancar(CarbonImmutable $data, int $intervalo, string $unidade): CarbonImmutable
    {
        return match ($unidade) {
            'dia' => $data->addDays($intervalo),
            'semana' => $data->addWeeks($intervalo),
            // addMonthsNoOverflow: 31 de janeiro + 1 mes = 28/29 de fevereiro,
            // e nao 2 ou 3 de marco.
            'mes' => $data->addMonthsNoOverflow($intervalo),
            'ano' => $data->addYearsNoOverflow($intervalo),
            default => $data->addMonthsNoOverflow($intervalo),
        };
    }

    private function criarOcorrencia(Compromisso $serie, CarbonImmutable $data): Compromisso
    {
        return DB::transaction(fn () => Compromisso::query()->create([
            'titulo' => $serie->titulo,
            'descricao' => $serie->descricao,
            'categoria' => $serie->categoria,
            'tipo' => $serie->tipo,
            'entidade' => $serie->entidade,
            'data' => $data->toDateString(),
            'hora' => $serie->hora,
            'valor' => $serie->valor,
            'estado' => 'pendente',
            'recorrencia' => 'nenhuma',
            'compromisso_pai_id' => $serie->id,
            'antecedencia_aviso_dias' => $serie->antecedencia_aviso_dias,
            'campanha_id' => $serie->campanha_id,
            'parcela_id' => $serie->parcela_id,
            'cultura_id' => $serie->cultura_id,
            'maquina_id' => $serie->maquina_id,
            'funcionario_id' => $serie->funcionario_id,
            'referencia_externa' => $serie->referencia_externa === null
                ? null
                : $serie->referencia_externa.'-'.$data->toDateString(),
            'notas' => $serie->notas,
        ]));
    }
}
