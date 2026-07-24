<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Funcionario;
use App\Models\Lote;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\Terreno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResolvedorReferencias
{
    public function resolverCampanha(int|string|null $valor, ?int $ano = null): Campanha
    {
        /** @var Campanha $campanha */
        $campanha = $this->resolverModelo(
            Campanha::query()->with('cultura'),
            $valor,
            'campanha',
            function (Builder $query, string $texto) use ($ano): void {
                $anoResolvido = $ano ?? $this->extrairAno($texto);

                if ($anoResolvido !== null) {
                    $query->where('ano', $anoResolvido);
                }

                $nomeCultura = trim((string) preg_replace('/\b\d{4}\b/', '', $texto));

                if ($nomeCultura !== '') {
                    $query->whereHas('cultura', function (Builder $query) use ($nomeCultura): void {
                        $query->where('nome', $nomeCultura);
                    });
                }
            },
            fn (Campanha $campanha) => [
                'id' => $campanha->id,
                'nome' => trim(($campanha->cultura?->nome ? $campanha->cultura->nome.' ' : '').$campanha->ano),
                'ano' => $campanha->ano,
            ]
        );

        return $campanha;
    }

    public function resolverParcela(int|string|null $valor): Parcela
    {
        /** @var Parcela $parcela */
        $parcela = $this->resolverModelo(
            Parcela::query(),
            $valor,
            'parcela',
            fn (Builder $query, string $texto) => $query
                ->where('nome', $texto)
                ->orWhere('numero_parcela', $texto),
            fn (Parcela $parcela) => [
                'id' => $parcela->id,
                'nome' => $parcela->nome,
                'codigo' => $parcela->numero_parcela,
            ]
        );

        return $parcela;
    }

    public function resolverCultura(int|string|null $valor): Cultura
    {
        /** @var Cultura $cultura */
        $cultura = $this->resolverModelo(
            Cultura::query(),
            $valor,
            'cultura',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Cultura $cultura) => [
                'id' => $cultura->id,
                'nome' => $cultura->nome,
                'parcela_id' => $cultura->parcela_id,
            ]
        );

        return $cultura;
    }

    public function resolverOperacao(int|string|null $valor): Operacao
    {
        /** @var Operacao $operacao */
        $operacao = $this->resolverModelo(
            Operacao::query(),
            $valor,
            'operacao',
            fn (Builder $query, string $texto) => $query->where('tipo', $texto),
            fn (Operacao $operacao) => [
                'id' => $operacao->id,
                'tipo' => $operacao->tipo,
                'data' => $operacao->data_hora_inicio?->toDateString(),
            ]
        );

        return $operacao;
    }

    public function resolverProduto(
        int|string|null $valor,
        bool $criarSeInexistente = false,
        ?string $tipo = null
    ): Produto {
        try {
            /** @var Produto $produto */
            $produto = $this->resolverModelo(
                Produto::query(),
                $valor,
                'produto',
                fn (Builder $query, string $texto) => $query
                    ->where('numero_autorizacao_dgav', $texto)
                    ->orWhere('nome', $texto),
                fn (Produto $produto) => [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                ]
            );

            return $produto;
        } catch (ValidationException $exception) {
            if (! $criarSeInexistente || $valor === null || $valor === '') {
                throw $exception;
            }

            return Produto::query()->create([
                'nome' => (string) $valor,
                'tipo' => $tipo ?: 'outro',
            ]);
        }
    }

    public function resolverMaquina(int|string|null $valor): Maquina
    {
        /** @var Maquina $maquina */
        $maquina = $this->resolverModelo(
            Maquina::query(),
            $valor,
            'maquina',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Maquina $maquina) => [
                'id' => $maquina->id,
                'nome' => $maquina->nome,
            ]
        );

        return $maquina;
    }

    public function resolverFuncionario(int|string|null $valor): Funcionario
    {
        /** @var Funcionario $funcionario */
        $funcionario = $this->resolverModelo(
            Funcionario::query(),
            $valor,
            'funcionario',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Funcionario $funcionario) => [
                'id' => $funcionario->id,
                'nome' => $funcionario->nome,
            ]
        );

        return $funcionario;
    }

    public function resolverTerreno(int|string|null $valor): Terreno
    {
        /** @var Terreno $terreno */
        $terreno = $this->resolverModelo(
            Terreno::query(),
            $valor,
            'terreno',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Terreno $terreno) => [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
            ]
        );

        return $terreno;
    }

    public function resolverColheita(int|string|null $valor): Colheita
    {
        /** @var Colheita $colheita */
        $colheita = $this->resolverModelo(
            Colheita::query(),
            $valor,
            'colheita',
            fn (Builder $query, string $texto) => $query->where('referencia_externa', $texto),
            fn (Colheita $colheita) => [
                'id' => $colheita->id,
                'data' => $colheita->data_colheita?->toDateString(),
                'referencia_externa' => $colheita->referencia_externa,
            ]
        );

        return $colheita;
    }

    public function resolverLote(int|string|null $valor): Lote
    {
        /** @var Lote $lote */
        $lote = $this->resolverModelo(
            Lote::query(),
            $valor,
            'lote',
            fn (Builder $query, string $texto) => $query->where('numero_lote', $texto),
            fn (Lote $lote) => [
                'id' => $lote->id,
                'codigo' => $lote->numero_lote,
            ]
        );

        return $lote;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function resolverModelo(
        Builder $query,
        int|string|null $valor,
        string $campo,
        callable $aplicarTexto,
        callable $formatarCandidato
    ): Model {
        if ($valor === null || $valor === '') {
            throw ValidationException::withMessages([
                $campo => ["Referencia de {$campo} em falta."],
            ]);
        }

        if (is_numeric($valor)) {
            $porId = (clone $query)->whereKey((int) $valor)->first();

            if ($porId) {
                return $porId;
            }
        }

        $texto = trim((string) $valor);
        $consultaTexto = clone $query;
        $aplicarTexto($consultaTexto, $texto);

        /** @var Collection<int, Model> $resultados */
        $resultados = $consultaTexto->limit(6)->get();

        if ($resultados->count() === 1) {
            return $resultados->first();
        }

        if ($resultados->count() > 1) {
            throw ValidationException::withMessages([
                $campo => [
                    "Referencia de {$campo} ambigua.",
                    [
                        'valor' => $valor,
                        'candidatos' => $resultados->map($formatarCandidato)->values()->all(),
                    ],
                ],
            ]);
        }

        throw ValidationException::withMessages([
            $campo => ["Referencia de {$campo} nao encontrada: {$valor}."],
        ]);
    }

    private function extrairAno(string $texto): ?int
    {
        preg_match('/\b(19|20)\d{2}\b/', $texto, $matches);

        return isset($matches[0]) ? (int) $matches[0] : null;
    }
}
