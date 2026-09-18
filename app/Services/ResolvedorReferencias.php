<?php

namespace App\Services;

use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Equipa;
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
                // Uma campanha geral tem nome proprio ("Pereiras 2026"); as
                // antigas derivam o nome da cultura + ano.
                $query->where(function (Builder $externo) use ($texto, $ano): void {
                    $externo->where('nome', $texto);

                    $externo->orWhere(function (Builder $porCultura) use ($texto, $ano): void {
                        $anoResolvido = $ano ?? $this->extrairAno($texto);

                        if ($anoResolvido !== null) {
                            $porCultura->where('ano', $anoResolvido);
                        }

                        $nomeCultura = trim((string) preg_replace('/\b\d{4}\b/', '', $texto));

                        if ($nomeCultura !== '') {
                            $porCultura->whereHas('cultura', function (Builder $query) use ($nomeCultura): void {
                                $query->where('nome', $nomeCultura);
                            });
                        }
                    });
                });
            },
            fn (Campanha $campanha) => [
                'id' => $campanha->id,
                'nome' => $campanha->nome_completo,
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
            ],
            ['nome', 'numero_parcela']
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
            ],
            ['tipo']
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
                    ->orWhere('codigo_interno', $texto)
                    ->orWhere('nome', $texto),
                fn (Produto $produto) => [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'numero_autorizacao_dgav' => $produto->numero_autorizacao_dgav,
                ],
                ['nome', 'codigo_interno', 'numero_autorizacao_dgav']
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
            ],
            ['referencia_externa']
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
            ],
            ['numero_lote']
        );

        return $lote;
    }

    public function resolverAlfaia(int|string|null $valor): Alfaia
    {
        /** @var Alfaia $alfaia */
        $alfaia = $this->resolverModelo(
            Alfaia::query(),
            $valor,
            'alfaia',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Alfaia $alfaia) => [
                'id' => $alfaia->id,
                'nome' => $alfaia->nome,
            ]
        );

        return $alfaia;
    }

    public function resolverEquipa(int|string|null $valor): Equipa
    {
        /** @var Equipa $equipa */
        $equipa = $this->resolverModelo(
            Equipa::query(),
            $valor,
            'equipa',
            fn (Builder $query, string $texto) => $query->where('nome', $texto),
            fn (Equipa $equipa) => [
                'id' => $equipa->id,
                'nome' => $equipa->nome,
            ]
        );

        return $equipa;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function resolverModelo(
        Builder $query,
        int|string|null $valor,
        string $campo,
        callable $aplicarTexto,
        callable $formatarCandidato,
        array $colunasParecidas = ['nome']
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

        // Nao encontrado: em vez de um "nao existe" seco, dizer o que existe
        // parecido. Quem envia a fatura escreve o nome de cabeca ("Amanha de
        // pes" com ou sem til) e nao tem a lista a mao.
        $parecidos = $this->parecidos($query, $texto, $colunasParecidas);

        $mensagens = ["Referencia de {$campo} nao encontrada: {$valor}."];

        if ($parecidos->isNotEmpty()) {
            $mensagens[] = [
                'valor' => $valor,
                'candidatos' => $parecidos->map($formatarCandidato)->values()->all(),
            ];
        }

        throw ValidationException::withMessages([$campo => $mensagens]);
    }

    /**
     * Registos com nome parecido com o que veio no pedido.
     *
     * Duas tentativas na mesma consulta: o texto em qualquer sitio do nome, e
     * nomes comecados pela primeira palavra.
     *
     * Em producao (MySQL, collation utf8mb4 ..._ci) o LIKE ignora acentos e
     * "Amanha" encontra "Amanhã". No SQLite dos testes nao ignora - nao
     * escrever testes que dependam disso.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $colunas
     * @return Collection<int, Model>
     */
    private function parecidos(Builder $query, string $texto, array $colunas): Collection
    {
        if ($colunas === [] || mb_strlen($texto) < 3) {
            return collect();
        }

        $primeira = preg_split('/\s+/u', $texto)[0] ?? '';

        return (clone $query)
            ->where(function (Builder $externo) use ($texto, $primeira, $colunas): void {
                foreach ($colunas as $coluna) {
                    $externo->orWhere($coluna, 'like', '%'.$texto.'%');

                    if (mb_strlen($primeira) >= 3) {
                        $externo->orWhere($coluna, 'like', $primeira.'%');
                    }
                }
            })
            ->limit(5)
            ->get();
    }

    private function extrairAno(string $texto): ?int
    {
        preg_match('/\b(19|20)\d{2}\b/', $texto, $matches);

        return isset($matches[0]) ? (int) $matches[0] : null;
    }
}
