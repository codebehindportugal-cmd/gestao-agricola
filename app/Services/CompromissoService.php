<?php

namespace App\Services;

use App\Models\Compromisso;
use App\Models\Custo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompromissoService
{
    public function __construct(private readonly GeradorCompromissos $gerador)
    {
    }

    /**
     * Marca como concluido, regista o Custo (se houver valor) e garante que a
     * proxima ocorrencia da serie existe.
     *
     * @return array{compromisso: Compromisso, custo: ?Custo, proxima: ?Compromisso}
     */
    public function concluir(
        Compromisso $compromisso,
        float|string|null $valorPago = null,
        ?string $dataConclusao = null,
        bool $criarCusto = true
    ): array {
        return DB::transaction(function () use ($compromisso, $valorPago, $dataConclusao, $criarCusto) {
            $valor = $valorPago !== null && $valorPago !== ''
                ? (float) $valorPago
                : (float) ($compromisso->valor ?? 0);

            $data = $dataConclusao ?: CarbonImmutable::now()->toDateString();

            $compromisso->update([
                'estado' => 'concluido',
                'data_conclusao' => $data,
                'valor_pago' => $valor > 0 ? $valor : null,
            ]);

            $custo = null;

            // Nao duplica: se ja tinha custo associado, mantem o que existe.
            if ($criarCusto && $valor > 0 && $compromisso->custo_id === null) {
                $custo = Custo::query()->create([
                    'descricao' => $compromisso->titulo,
                    'tipo' => Compromisso::CATEGORIA_PARA_TIPO_CUSTO[$compromisso->categoria] ?? 'outro',
                    'valor' => $valor,
                    'data_custo' => $data,
                    'campanha_id' => $compromisso->campanha_id,
                    'cultura_id' => $compromisso->cultura_id,
                    'parcela_id' => $compromisso->parcela_id,
                    'maquina_id' => $compromisso->maquina_id,
                    'funcionario_id' => $compromisso->funcionario_id,
                    'referencia_externa' => 'compromisso-'.$compromisso->id,
                    'observacoes' => $compromisso->entidade ? "Entidade: {$compromisso->entidade}" : null,
                ]);

                $compromisso->update(['custo_id' => $custo->id]);
            }

            $proxima = $this->garantirProxima($compromisso);

            return [
                'compromisso' => $compromisso->refresh(),
                'custo' => $custo,
                'proxima' => $proxima,
            ];
        });
    }

    public function reabrir(Compromisso $compromisso): Compromisso
    {
        $compromisso->update([
            'estado' => 'pendente',
            'data_conclusao' => null,
            'valor_pago' => null,
        ]);

        return $compromisso->refresh();
    }

    /**
     * Ao concluir uma ocorrencia de uma serie, garante que ha pelo menos uma
     * ocorrencia futura pendente. Devolve-a quando foi criada agora.
     */
    private function garantirProxima(Compromisso $compromisso): ?Compromisso
    {
        $serie = $compromisso->compromisso_pai_id !== null
            ? $compromisso->pai
            : $compromisso;

        if ($serie === null || $serie->passoRecorrencia() === null) {
            return null;
        }

        $criados = $this->gerador->gerar($serie);

        return $criados[0] ?? null;
    }
}
