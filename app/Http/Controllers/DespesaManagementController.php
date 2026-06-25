<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DespesaManagementController extends Controller
{
    public const CATEGORIAS = [
        'combustivel',
        'sementes',
        'fertilizantes',
        'fitofarmaceuticos',
        'equipamento',
        'mao_obra',
        'outro',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Despesa::class);

        $filters = $request->only(['search', 'categoria', 'mes', 'ano']);

        $mes = (int) ($filters['mes'] ?? now()->month);
        $ano = (int) ($filters['ano'] ?? now()->year);

        $query = Despesa::query()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('titulo', 'like', "%{$s}%")
                    ->orWhere('fornecedor', 'like', "%{$s}%")
                    ->orWhere('numero_fatura', 'like', "%{$s}%");
            }))
            ->when($filters['categoria'] ?? null, fn ($q, $cat) => $q->where('categoria', $cat))
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderByDesc('data');

        $despesas = $query->paginate(20)->withQueryString()->through(fn (Despesa $d) => [
            'id' => $d->id,
            'titulo' => $d->titulo,
            'numero_fatura' => $d->numero_fatura,
            'fornecedor' => $d->fornecedor,
            'valor' => (float) $d->valor,
            'data' => $d->data?->format('Y-m-d'),
            'categoria' => $d->categoria,
            'ficheiro_path' => $d->ficheiro_path,
            'ficheiro_url' => $d->ficheiro_path ? Storage::disk('public')->url($d->ficheiro_path) : null,
            'notas' => $d->notas,
        ]);

        $resumoMes = $this->buildResumoMes($mes, $ano);
        $resumoMesAnterior = $this->buildResumoMes($mes === 1 ? 12 : $mes - 1, $mes === 1 ? $ano - 1 : $ano);

        return Inertia::render('Despesas/Index', [
            'despesas' => $despesas,
            'filters' => array_merge($filters, ['mes' => $mes, 'ano' => $ano]),
            'categorias' => self::CATEGORIAS,
            'resumoMes' => $resumoMes,
            'resumoMesAnterior' => $resumoMesAnterior,
            'can' => [
                'create' => $request->user()->can('create', Despesa::class),
                'update' => $request->user()->can('update', Despesa::class, new Despesa()),
                'delete' => $request->user()->can('delete', Despesa::class, new Despesa()),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data' => ['required', 'date'],
            'categoria' => ['required', 'string', 'in:' . implode(',', self::CATEGORIAS)],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
        ]);

        if ($request->hasFile('ficheiro')) {
            $data['ficheiro_path'] = $request->file('ficheiro')->store('despesas', 'public');
        }

        unset($data['ficheiro']);
        Despesa::create($data);

        return redirect()->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Despesa registada com sucesso.');
    }

    public function update(Request $request, Despesa $despesa): RedirectResponse
    {
        $this->authorize('update', $despesa);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data' => ['required', 'date'],
            'categoria' => ['required', 'string', 'in:' . implode(',', self::CATEGORIAS)],
            'notas' => ['nullable', 'string'],
            'ficheiro' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
        ]);

        if ($request->hasFile('ficheiro')) {
            if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
                Storage::disk('public')->delete($despesa->ficheiro_path);
            }
            $data['ficheiro_path'] = $request->file('ficheiro')->store('despesas', 'public');
        }

        unset($data['ficheiro']);
        $despesa->update($data);

        return redirect()->route('app.despesas.index', $request->only(['mes', 'ano']))
            ->with('success', 'Despesa atualizada com sucesso.');
    }

    public function destroy(Despesa $despesa): RedirectResponse
    {
        $this->authorize('delete', $despesa);

        if ($despesa->ficheiro_path && Storage::disk('public')->exists($despesa->ficheiro_path)) {
            Storage::disk('public')->delete($despesa->ficheiro_path);
        }

        $despesa->delete();

        return back()->with('success', 'Despesa eliminada com sucesso.');
    }

    public function exportarResumoMensal(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes = (int) ($request->query('mes', now()->month));
        $ano = (int) ($request->query('ano', now()->year));

        $resumo = $this->buildResumoMes($mes, $ano);
        $despesas = Despesa::query()
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get()
            ->map(fn (Despesa $d) => [
                'titulo' => $d->titulo,
                'fornecedor' => $d->fornecedor ?? '-',
                'numero_fatura' => $d->numero_fatura ?? '-',
                'categoria' => $d->categoria,
                'valor' => (float) $d->valor,
                'data' => $d->data?->format('d/m/Y'),
            ]);

        $nomeMes = \Carbon\Carbon::create($ano, $mes, 1)->translatedFormat('F Y');

        return view('despesas.resumo_mensal', compact('resumo', 'despesas', 'nomeMes', 'mes', 'ano'));
    }

    public function exportarCsv(Request $request)
    {
        $this->authorize('viewAny', Despesa::class);

        $mes = (int) ($request->query('mes', now()->month));
        $ano = (int) ($request->query('ano', now()->year));

        $despesas = Despesa::query()
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();

        $nomeMes = \Carbon\Carbon::create($ano, $mes, 1)->format('Y-m');
        $filename = "despesas-{$nomeMes}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($despesas) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Data', 'Título', 'Fornecedor', 'Nº Fatura', 'Categoria', 'Valor (€)', 'Notas'], ';');

            foreach ($despesas as $d) {
                fputcsv($handle, [
                    $d->data?->format('d/m/Y'),
                    $d->titulo,
                    $d->fornecedor ?? '',
                    $d->numero_fatura ?? '',
                    $d->categoria,
                    number_format((float) $d->valor, 2, ',', '.'),
                    $d->notas ?? '',
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildResumoMes(int $mes, int $ano): array
    {
        $despesas = Despesa::query()
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'categoria']);

        $total = (float) $despesas->sum('valor');

        $porCategoria = collect(self::CATEGORIAS)->mapWithKeys(fn ($cat) => [
            $cat => (float) $despesas->where('categoria', $cat)->sum('valor'),
        ])->all();

        return [
            'mes' => $mes,
            'ano' => $ano,
            'total' => $total,
            'por_categoria' => $porCategoria,
            'count' => $despesas->count(),
        ];
    }
}
