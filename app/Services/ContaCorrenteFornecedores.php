<?php

namespace App\Services;

use App\Models\Despesa;
use App\Models\Fornecedor;
use App\Models\PagamentoFornecedor;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * As contas com os fornecedores: o que se comprou (faturas), o que se pagou
 * (recibos) e quanto falta pagar a cada um.
 *
 * Regras:
 * - entram na divida as faturas com fornecedor que nao foram pagas no acto
 *   (FR/FS/VD ou marcadas a mao como pagas no acto);
 * - o saldo do fornecedor e faturado - pago. Um recibo que pague mais do que
 *   as faturas que indica deixa o resto "por imputar", mas o saldo ja o conta;
 * - o estado de cada fatura (por pagar, parcial, paga) vem do que os recibos
 *   lhe imputaram.
 */
class ContaCorrenteFornecedores
{
    private const TOLERANCIA = 0.005;

    /** Faturas que contam para a divida. */
    public function faturasQuery(): Builder
    {
        return Despesa::query()
            ->whereNotNull('fornecedor_id')
            ->where('pago_no_ato', false)
            ->select('despesas.*')
            ->selectSub($this->somaPagaSub(), 'valor_pago');
    }

    /**
     * Saldo de cada fornecedor com movimento.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function saldos(?string $pesquisa = null): Collection
    {
        $faturas = $this->faturasQuery()
            ->get(['despesas.id', 'despesas.fornecedor_id', 'despesas.data', 'despesas.valor'])
            ->groupBy('fornecedor_id');

        $pagamentos = PagamentoFornecedor::query()
            ->selectRaw('fornecedor_id, SUM(valor) as total, MAX(data) as ultimo, COUNT(*) as quantos')
            ->groupBy('fornecedor_id')
            ->get()
            ->keyBy('fornecedor_id');

        $ids = $faturas->keys()->merge($pagamentos->keys())->unique()->values();

        return Fornecedor::query()
            ->whereIn('id', $ids)
            ->when($pesquisa, fn ($q, $s) => $q->where(fn ($sub) => $sub->where('nome', 'like', "%{$s}%")->orWhere('nif', 'like', "%{$s}%")))
            ->orderBy('nome')
            ->get()
            ->map(function (Fornecedor $f) use ($faturas, $pagamentos) {
                $dele = $faturas->get($f->id, collect());
                $faturado = round((float) $dele->sum('valor'), 2);
                $imputado = round((float) $dele->sum('valor_pago'), 2);
                $pago = round((float) ($pagamentos->get($f->id)?->total ?? 0), 2);
                $abertas = $dele->filter(fn ($d) => $this->emFalta($d) > self::TOLERANCIA);

                return [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'nif' => $f->nif,
                    'faturado' => $faturado,
                    'pago' => $pago,
                    'saldo' => round($faturado - $pago, 2),
                    'por_imputar' => round(max(0, $pago - $imputado), 2),
                    'faturas' => $dele->count(),
                    'faturas_em_aberto' => $abertas->count(),
                    'em_aberto_desde' => $abertas->min(fn ($d) => $d->data?->format('Y-m-d')),
                    'ultimo_pagamento' => $pagamentos->get($f->id)?->ultimo
                        ? substr((string) $pagamentos->get($f->id)->ultimo, 0, 10)
                        : null,
                ];
            })
            ->sortByDesc('saldo')
            ->values();
    }

    /**
     * Faturas do fornecedor com o que esta pago e o que falta.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function faturas(Fornecedor $fornecedor, bool $soEmAberto = false): Collection
    {
        return $this->faturasQuery()
            ->where('fornecedor_id', $fornecedor->id)
            ->orderBy('data')
            ->orderBy('id')
            ->get()
            ->map(fn (Despesa $d) => $this->formatarFatura($d))
            ->when($soEmAberto, fn (Collection $c) => $c->filter(fn ($f) => $f['em_falta'] > self::TOLERANCIA))
            ->values();
    }

    /**
     * Extrato: faturas e recibos por data, com o saldo acumulado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extrato(Fornecedor $fornecedor): array
    {
        $movimentos = collect();

        foreach ($this->faturasQuery()->where('fornecedor_id', $fornecedor->id)->get() as $d) {
            $movimentos->push([
                'tipo' => 'fatura',
                'id' => $d->id,
                'data' => $d->data?->format('Y-m-d'),
                'documento' => $d->numero_fatura ?: $d->titulo,
                'descricao' => $d->titulo,
                'debito' => round((float) $d->valor, 2),
                'credito' => 0.0,
            ]);
        }

        foreach ($fornecedor->pagamentos()->with('despesas:id,numero_fatura')->get() as $p) {
            $movimentos->push([
                'tipo' => 'pagamento',
                'id' => $p->id,
                'data' => $p->data?->format('Y-m-d'),
                'documento' => $p->numero_recibo ?: 'Pagamento',
                'descricao' => $p->despesas->pluck('numero_fatura')->filter()->implode(', ') ?: ($p->notas ?: 'Sem faturas indicadas'),
                'debito' => 0.0,
                'credito' => round((float) $p->valor, 2),
            ]);
        }

        $saldo = 0.0;

        return $movimentos
            ->sortBy(fn ($m) => $m['data'].($m['tipo'] === 'fatura' ? '0' : '1').str_pad((string) $m['id'], 10, '0', STR_PAD_LEFT))
            ->values()
            ->map(function ($m) use (&$saldo) {
                $saldo = round($saldo + $m['debito'] - $m['credito'], 2);

                return $m + ['saldo' => $saldo];
            })
            ->all();
    }

    /**
     * Regista um recibo.
     *
     * $faturas: lista de ['despesa_id' => ?, 'numero_fatura' => ?, 'valor' => ?].
     * Sem valor numa linha, imputa-se o que falta pagar dessa fatura (ate ao
     * que resta do recibo). Sem faturas e com $imputarAuto, paga as faturas
     * em aberto mais antigas primeiro.
     *
     * @return array{pagamento: PagamentoFornecedor, avisos: array<int, string>}
     */
    public function registarPagamento(Fornecedor $fornecedor, array $dados, array $faturas = [], bool $imputarAuto = false): array
    {
        return DB::transaction(function () use ($fornecedor, $dados, $faturas, $imputarAuto) {
            $avisos = [];
            $pendentes = [];

            $pagamento = $fornecedor->pagamentos()->create([
                'data' => $dados['data'],
                'valor' => round((float) $dados['valor'], 2),
                'numero_recibo' => $dados['numero_recibo'] ?? null,
                'metodo' => $dados['metodo'] ?? null,
                'ficheiro_path' => $dados['ficheiro_path'] ?? null,
                'notas' => $dados['notas'] ?? null,
                'referencia_externa' => $dados['referencia_externa'] ?? null,
            ]);

            $restante = (float) $pagamento->valor;

            foreach ($faturas as $linha) {
                $despesa = $this->encontrarFatura($fornecedor, $linha['despesa_id'] ?? null, $linha['numero_fatura'] ?? null);
                $pedido = isset($linha['valor']) && $linha['valor'] !== '' ? round((float) $linha['valor'], 2) : null;

                if (! $despesa) {
                    $numero = $linha['numero_fatura'] ?? ('#'.($linha['despesa_id'] ?? '?'));
                    $pendentes[] = array_filter(['numero_fatura' => $numero, 'valor' => $pedido], fn ($v) => $v !== null);
                    $avisos[] = "A fatura {$numero} nao foi encontrada entre as faturas em divida deste fornecedor (ainda nao registada, ou o numero nao bate). Fica pendente e liga-se a este recibo quando for registada com esse numero.";

                    continue;
                }

                $emFalta = $this->emFalta($despesa);
                $valor = $pedido ?? min($emFalta, $restante);

                if ($pedido !== null && $pedido - $emFalta > self::TOLERANCIA) {
                    $avisos[] = sprintf('A fatura %s so tinha %.2f € por pagar; o recibo indica %.2f €. Imputou-se %.2f €.', $despesa->numero_fatura, $emFalta, $pedido, $emFalta);
                    $valor = $emFalta;
                }

                if ($valor - $restante > self::TOLERANCIA) {
                    $avisos[] = sprintf('O recibo nao chega para a fatura %s: faltam %.2f €.', $despesa->numero_fatura, $valor - $restante);
                    $valor = $restante;
                }

                if ($valor <= self::TOLERANCIA) {
                    if ($emFalta <= self::TOLERANCIA) {
                        $avisos[] = "A fatura {$despesa->numero_fatura} ja estava paga.";
                    }

                    continue;
                }

                $this->imputar($pagamento, $despesa, $valor);
                $restante = round($restante - $valor, 2);
            }

            if ($faturas === [] && $imputarAuto) {
                $restante = $this->imputarFifo($pagamento, $fornecedor, $restante);
            }

            if ($pendentes !== []) {
                $pagamento->update(['faturas_pendentes' => $pendentes]);
            }

            if ($restante > self::TOLERANCIA) {
                $avisos[] = sprintf('Ficam %.2f € por imputar a faturas (adiantamento ou faturas por registar).', $restante);
            }

            return ['pagamento' => $pagamento->fresh(['despesas']), 'avisos' => $avisos];
        });
    }

    /**
     * Pega no dinheiro dos recibos que ficou por imputar e paga com ele as
     * faturas em aberto mais antigas. Devolve o valor imputado.
     */
    public function imputarPorImputar(Fornecedor $fornecedor): float
    {
        return DB::transaction(function () use ($fornecedor) {
            $total = 0.0;

            foreach ($fornecedor->pagamentos()->orderBy('data')->orderBy('id')->get() as $pagamento) {
                $livre = $pagamento->valor_por_imputar;

                if ($livre <= self::TOLERANCIA) {
                    continue;
                }

                $sobra = $this->imputarFifo($pagamento, $fornecedor, $livre);
                $total += $livre - $sobra;
            }

            return round($total, 2);
        });
    }

    /**
     * Uma fatura acabada de registar pode ja ter sido paga por um recibo que a
     * indicava (ficou em faturas_pendentes). Liga-os.
     */
    public function ligarPagamentosPendentes(Despesa $despesa): void
    {
        if ($despesa->pago_no_ato || ! $despesa->fornecedor_id || ! $despesa->numero_fatura) {
            return;
        }

        $chave = $this->normalizarNumero($despesa->numero_fatura);

        $pagamentos = PagamentoFornecedor::query()
            ->where('fornecedor_id', $despesa->fornecedor_id)
            ->whereNotNull('faturas_pendentes')
            ->orderBy('data')
            ->get();

        foreach ($pagamentos as $pagamento) {
            $pendentes = collect($pagamento->faturas_pendentes ?? []);
            $indice = $pendentes->search(fn ($p) => $this->numerosIguais($p['numero_fatura'] ?? '', $despesa->numero_fatura, $chave));

            if ($indice === false) {
                continue;
            }

            $atual = $this->faturasQuery()->find($despesa->id);
            $emFalta = $atual ? $this->emFalta($atual) : 0.0;
            $valor = min(
                (float) ($pendentes[$indice]['valor'] ?? $emFalta),
                $emFalta,
                $pagamento->valor_por_imputar
            );

            if ($valor > self::TOLERANCIA && ! $pagamento->despesas()->whereKey($despesa->id)->exists()) {
                $this->imputar($pagamento, $despesa, $valor);
            }

            $pendentes->forget($indice);
            $pagamento->update(['faturas_pendentes' => $pendentes->isEmpty() ? null : $pendentes->values()->all()]);
        }
    }

    /**
     * Junta dois fornecedores que sao o mesmo (nomes lidos de maneiras
     * diferentes nas faturas). Tudo passa para $destino.
     */
    public function juntar(Fornecedor $destino, Fornecedor $origem): void
    {
        if ($destino->is($origem)) {
            return;
        }

        DB::transaction(function () use ($destino, $origem) {
            Despesa::withTrashed()->where('fornecedor_id', $origem->id)->update(['fornecedor_id' => $destino->id]);
            PagamentoFornecedor::withTrashed()->where('fornecedor_id', $origem->id)->update(['fornecedor_id' => $destino->id]);
            Produto::query()->where('fornecedor_id', $origem->id)->update(['fornecedor_id' => $destino->id]);

            foreach (['nif', 'telefone', 'email', 'contacto', 'localizacao'] as $campo) {
                if (blank($destino->{$campo}) && filled($origem->{$campo})) {
                    $destino->{$campo} = $origem->{$campo};
                }
            }

            $destino->save();
            $origem->delete();
        });
    }

    public function encontrarFatura(Fornecedor $fornecedor, mixed $despesaId, ?string $numero): ?Despesa
    {
        $base = fn () => $this->faturasQuery()->where('fornecedor_id', $fornecedor->id);

        if ($despesaId) {
            return $base()->whereKey((int) $despesaId)->first();
        }

        if (blank($numero)) {
            return null;
        }

        $chave = $this->normalizarNumero($numero);
        $candidatas = $base()->whereNotNull('numero_fatura')->get();

        $exactas = $candidatas->filter(fn (Despesa $d) => $this->normalizarNumero($d->numero_fatura) === $chave);

        if ($exactas->count() === 1) {
            return $exactas->first();
        }

        // O recibo escreve muitas vezes a serie de outra maneira ("FT A/145"
        // vs "FT 2026A/145"). Mesmo tipo de documento e mesmo numero final,
        // se for so uma, e essa.
        $parecidas = $candidatas->filter(fn (Despesa $d) => $this->numerosIguais($numero, $d->numero_fatura));

        return $parecidas->count() === 1 ? $parecidas->first() : null;
    }

    /** @return array<string, mixed> */
    public function formatarFatura(Despesa $d): array
    {
        $pago = round((float) ($d->valor_pago ?? 0), 2);
        $emFalta = $this->emFalta($d);

        return [
            'id' => $d->id,
            'data' => $d->data?->format('Y-m-d'),
            'numero_fatura' => $d->numero_fatura,
            'titulo' => $d->titulo,
            'valor' => round((float) $d->valor, 2),
            'pago' => $pago,
            'em_falta' => $emFalta,
            'estado' => $emFalta <= self::TOLERANCIA ? 'paga' : ($pago > self::TOLERANCIA ? 'parcial' : 'por_pagar'),
            'dias' => $d->data ? (int) $d->data->diffInDays(now()->startOfDay()) : null,
        ];
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    private function somaPagaSub()
    {
        return DB::table('pagamento_fornecedor_despesa as pfd')
            ->join('pagamentos_fornecedores as pf', 'pf.id', '=', 'pfd.pagamento_fornecedor_id')
            ->whereNull('pf.deleted_at')
            ->whereColumn('pfd.despesa_id', 'despesas.id')
            ->selectRaw('COALESCE(SUM(pfd.valor), 0)');
    }

    private function emFalta(Despesa $d): float
    {
        return round(max(0, (float) $d->valor - (float) ($d->valor_pago ?? 0)), 2);
    }

    private function imputar(PagamentoFornecedor $pagamento, Despesa $despesa, float $valor): void
    {
        $existente = $pagamento->despesas()->whereKey($despesa->id)->first();

        if ($existente) {
            $pagamento->despesas()->updateExistingPivot($despesa->id, [
                'valor' => round((float) $existente->pivot->valor + $valor, 2),
            ]);

            return;
        }

        $pagamento->despesas()->attach($despesa->id, ['valor' => round($valor, 2)]);
    }

    /** Paga as faturas em aberto mais antigas com $disponivel. Devolve o que sobra. */
    private function imputarFifo(PagamentoFornecedor $pagamento, Fornecedor $fornecedor, float $disponivel): float
    {
        $abertas = $this->faturasQuery()
            ->where('fornecedor_id', $fornecedor->id)
            ->orderBy('data')
            ->orderBy('id')
            ->get()
            ->filter(fn (Despesa $d) => $this->emFalta($d) > self::TOLERANCIA);

        foreach ($abertas as $despesa) {
            if ($disponivel <= self::TOLERANCIA) {
                break;
            }

            $valor = min($this->emFalta($despesa), $disponivel);
            $this->imputar($pagamento, $despesa, $valor);
            $disponivel = round($disponivel - $valor, 2);
        }

        return $disponivel;
    }

    private function normalizarNumero(?string $numero): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $numero) ?? '');
    }

    private function numerosIguais(string $a, ?string $b, ?string $chaveB = null): bool
    {
        $na = $this->normalizarNumero($a);
        $nb = $chaveB ?? $this->normalizarNumero($b);

        if ($na === '' || $nb === '') {
            return false;
        }

        if ($na === $nb) {
            return true;
        }

        preg_match('/^([A-Z]+)/', $na, $tipoA);
        preg_match('/^([A-Z]+)/', $nb, $tipoB);
        preg_match('/(\d+)\D*$/', (string) $a, $numA);
        preg_match('/(\d+)\D*$/', (string) $b, $numB);

        return isset($tipoA[1], $tipoB[1], $numA[1], $numB[1])
            && $tipoA[1] === $tipoB[1]
            && ltrim($numA[1], '0') === ltrim($numB[1], '0');
    }
}
