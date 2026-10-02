<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Fornecedor;
use App\Models\PagamentoFornecedor;
use App\Services\CompressorImagens;
use App\Services\ContaCorrenteFornecedores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ecra "Fornecedores": quanto se deve a cada um, as faturas em aberto e os
 * recibos. Usa as mesmas permissoes das despesas.
 */
class FornecedorContaController extends Controller
{
    public function __construct(private readonly ContaCorrenteFornecedores $conta)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Despesa::class);

        $saldos = $this->conta->saldos($request->query('search'));

        return Inertia::render('Fornecedores/Index', [
            'fornecedores' => $saldos,
            'filters' => $request->only('search'),
            'resumo' => [
                'em_divida' => round((float) $saldos->sum(fn ($s) => max(0, $s['saldo'])), 2),
                'com_divida' => $saldos->filter(fn ($s) => $s['saldo'] > 0.005)->count(),
                'faturas_em_aberto' => $saldos->sum('faturas_em_aberto'),
                'em_aberto_desde' => $saldos->pluck('em_aberto_desde')->filter()->min(),
            ],
            'faturasSemFornecedor' => Despesa::query()
                ->whereNull('fornecedor_id')
                ->where('pago_no_ato', false)
                ->count(),
        ]);
    }

    public function show(Request $request, Fornecedor $fornecedor): Response
    {
        $this->authorize('viewAny', Despesa::class);

        $saldo = $this->conta->saldos()->firstWhere('id', $fornecedor->id) ?? [
            'faturado' => 0.0, 'pago' => 0.0, 'saldo' => 0.0, 'por_imputar' => 0.0,
            'faturas_em_aberto' => 0, 'em_aberto_desde' => null,
        ];

        return Inertia::render('Fornecedores/Show', [
            'fornecedor' => $fornecedor->only(['id', 'nome', 'nif', 'telefone', 'email', 'contacto']),
            'saldo' => $saldo,
            'faturas' => $this->conta->faturas($fornecedor),
            'pagamentos' => $fornecedor->pagamentos()
                ->with('despesas:id,numero_fatura')
                ->orderByDesc('data')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PagamentoFornecedor $p) => [
                    'id' => $p->id,
                    'data' => $p->data?->format('Y-m-d'),
                    'valor' => round((float) $p->valor, 2),
                    'numero_recibo' => $p->numero_recibo,
                    'metodo' => $p->metodo,
                    'notas' => $p->notas,
                    'faturas' => $p->despesas->map(fn ($d) => [
                        'numero_fatura' => $d->numero_fatura,
                        'valor' => round((float) $d->pivot->valor, 2),
                    ])->values(),
                    'faturas_pendentes' => $p->faturas_pendentes ?? [],
                    'por_imputar' => $p->valor_por_imputar,
                    'ficheiro_url' => $p->ficheiro_path ? Storage::disk('public')->url($p->ficheiro_path) : null,
                ]),
            'extrato' => $this->conta->extrato($fornecedor),
            // Faturas-recibo e simplificadas: nao entram na divida, mas ficam
            // a vista para se poder corrigir uma que foi marcada mal.
            'pagasNoAto' => $fornecedor->despesas()
                ->where('pago_no_ato', true)
                ->orderByDesc('data')
                ->limit(50)
                ->get(['id', 'data', 'numero_fatura', 'titulo', 'valor'])
                ->map(fn (Despesa $d) => [
                    'id' => $d->id,
                    'data' => $d->data?->format('Y-m-d'),
                    'numero_fatura' => $d->numero_fatura,
                    'titulo' => $d->titulo,
                    'valor' => round((float) $d->valor, 2),
                ]),
            'outrosFornecedores' => Fornecedor::query()
                ->whereKeyNot($fornecedor->id)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'metodos' => PagamentoFornecedor::METODOS,
            'can' => [
                'create' => $request->user()->can('create', Despesa::class),
                'delete' => $request->user()->can('delete', new Despesa()),
            ],
        ]);
    }

    public function storePagamento(Request $request, Fornecedor $fornecedor): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $data = $request->validate([
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'numero_recibo' => ['nullable', 'string', 'max:100'],
            'metodo' => ['nullable', 'string', Rule::in(PagamentoFornecedor::METODOS)],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
            'imputar_automaticamente' => ['nullable', 'boolean'],
            'faturas' => ['nullable', 'array'],
            'faturas.*.despesa_id' => ['required', 'integer', 'exists:despesas,id'],
            'faturas.*.valor' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if (! empty($data['numero_recibo'])
            && $fornecedor->pagamentos()->where('numero_recibo', $data['numero_recibo'])->exists()) {
            return back()->withErrors(['numero_recibo' => 'Este recibo já está registado para este fornecedor.']);
        }

        if ($request->hasFile('ficheiro')) {
            $data['ficheiro_path'] = app(CompressorImagens::class)->guardar($request->file('ficheiro'), 'recibos');
        }

        $resultado = $this->conta->registarPagamento(
            $fornecedor,
            $data,
            $data['faturas'] ?? [],
            $request->boolean('imputar_automaticamente')
        );

        $msg = 'Recibo registado.';

        if ($resultado['avisos'] !== []) {
            $msg .= ' '.implode(' ', $resultado['avisos']);
        }

        return redirect()->route('app.fornecedores.show', $fornecedor)->with('success', $msg);
    }

    public function destroyPagamento(PagamentoFornecedor $pagamento): RedirectResponse
    {
        $this->authorize('delete', new Despesa());

        $fornecedor = $pagamento->fornecedor_id;
        $pagamento->delete();

        return redirect()->route('app.fornecedores.show', $fornecedor)->with('success', 'Recibo anulado; as faturas que pagava voltam a estar em aberto.');
    }

    public function imputar(Fornecedor $fornecedor): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $valor = $this->conta->imputarPorImputar($fornecedor);

        return back()->with('success', $valor > 0
            ? sprintf('Imputados %s € às faturas em aberto mais antigas.', number_format($valor, 2, ',', ' '))
            : 'Não havia valor por imputar ou faturas em aberto.');
    }

    public function pagoNoAto(Request $request, Despesa $despesa): RedirectResponse
    {
        $this->authorize('update', $despesa);

        $despesa->update(['pago_no_ato' => $request->boolean('pago_no_ato')]);

        return back()->with('success', $despesa->pago_no_ato
            ? 'Fatura marcada como paga no ato: deixa de contar na dívida.'
            : 'A fatura volta a contar na dívida ao fornecedor.');
    }

    public function juntar(Request $request, Fornecedor $fornecedor): RedirectResponse
    {
        $this->authorize('update', new Despesa());

        $data = $request->validate([
            'outro_id' => ['required', 'integer', 'exists:fornecedores,id', Rule::notIn([$fornecedor->id])],
        ]);

        $outro = Fornecedor::query()->findOrFail($data['outro_id']);
        $this->conta->juntar($fornecedor, $outro);

        return redirect()->route('app.fornecedores.show', $fornecedor)
            ->with('success', "As faturas e recibos de \"{$outro->nome}\" passaram para {$fornecedor->nome}.");
    }
}
