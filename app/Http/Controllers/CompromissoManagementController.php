<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompromissoRequest;
use App\Http\Requests\UpdateCompromissoRequest;
use App\Models\Campanha;
use App\Models\Compromisso;
use App\Models\Cultura;
use App\Models\Funcionario;
use App\Models\Maquina;
use App\Models\Parcela;
use App\Services\CompromissoService;
use App\Services\GeradorCompromissos;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompromissoManagementController extends Controller
{
    public function __construct(
        private readonly CompromissoService $servico,
        private readonly GeradorCompromissos $gerador
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Compromisso::class);

        $hoje = CarbonImmutable::now();
        $ano = (int) $request->integer('ano', $hoje->year);
        $mes = (int) $request->integer('mes', $hoje->month);
        $mes = max(1, min(12, $mes));

        $inicioMes = CarbonImmutable::create($ano, $mes, 1)->startOfMonth();
        $fimMes = $inicioMes->endOfMonth();

        // A grelha mostra semanas completas (segunda a domingo).
        $inicioGrelha = $inicioMes->startOfWeek(CarbonImmutable::MONDAY);
        $fimGrelha = $fimMes->endOfWeek(CarbonImmutable::SUNDAY);

        $filtros = $request->only(['categoria', 'estado', 'search']);

        $doMes = $this->consultaFiltrada($filtros)
            ->entre($inicioGrelha->toDateString(), $fimGrelha->toDateString())
            ->orderBy('data')
            ->orderBy('hora')
            ->get();

        $proximos = $this->consultaFiltrada($filtros)
            ->pendentes()
            ->whereDate('data', '>=', $hoje->toDateString())
            ->whereDate('data', '<=', $hoje->addDays(60)->toDateString())
            ->orderBy('data')
            ->limit(30)
            ->get();

        $atrasados = $this->consultaFiltrada($filtros)
            ->atrasados()
            ->orderBy('data')
            ->limit(30)
            ->get();

        return Inertia::render('Calendario/Index', [
            'ano' => $ano,
            'mes' => $mes,
            'inicioGrelha' => $inicioGrelha->toDateString(),
            'fimGrelha' => $fimGrelha->toDateString(),
            'filtros' => $filtros,
            'compromissos' => $this->formatarLista($doMes),
            'proximos' => $this->formatarLista($proximos),
            'atrasados' => $this->formatarLista($atrasados),
            'resumo' => [
                'total_mes' => round((float) $doMes->whereNotNull('valor')
                    ->whereBetween('data', [$inicioMes, $fimMes])->sum('valor'), 2),
                'por_pagar_mes' => round((float) $doMes->where('estado', 'pendente')
                    ->whereBetween('data', [$inicioMes, $fimMes])->sum('valor'), 2),
                'pendentes' => $doMes->where('estado', 'pendente')->count(),
                'atrasados' => $atrasados->count(),
            ],
            'opcoes' => [
                'categorias' => Compromisso::CATEGORIAS,
                'estados' => Compromisso::ESTADOS,
                'recorrencias' => Compromisso::RECORRENCIAS,
                'unidades' => Compromisso::UNIDADES_RECORRENCIA,
                'campanhas' => Campanha::query()->with('cultura:id,nome')->orderByDesc('ano')->get()
                    ->map(fn (Campanha $c) => [
                        'id' => $c->id,
                        'nome' => trim(($c->cultura?->nome ? $c->cultura->nome.' ' : '').$c->ano),
                    ])->values(),
                'parcelas' => Parcela::query()->orderBy('nome')->get(['id', 'nome']),
                'culturas' => Cultura::query()->orderBy('nome')->get(['id', 'nome']),
                'maquinas' => Maquina::query()->orderBy('nome')->get(['id', 'nome']),
                'funcionarios' => Funcionario::query()->orderBy('nome')->get(['id', 'nome']),
            ],
            'permissoes' => [
                'criar' => $request->user()?->can('create', Compromisso::class) ?? false,
                'editar' => $request->user()?->can('update', Compromisso::class) ?? false,
                'eliminar' => $request->user()?->can('delete', Compromisso::class) ?? false,
            ],
        ]);
    }

    public function store(StoreCompromissoRequest $request): RedirectResponse
    {
        $compromisso = Compromisso::query()->create($request->validated());

        $gerados = $this->gerador->gerar($compromisso);

        $msg = 'Compromisso criado.';

        if ($gerados !== []) {
            $msg .= ' Foram geradas '.count($gerados).' ocorrências futuras.';
        }

        return back()->with('success', $msg);
    }

    public function update(UpdateCompromissoRequest $request, Compromisso $compromisso): RedirectResponse
    {
        $compromisso->update($request->validated());

        if ($compromisso->compromisso_pai_id === null) {
            $this->gerador->gerar($compromisso->refresh());
        }

        return back()->with('success', 'Compromisso atualizado.');
    }

    public function concluir(Request $request, Compromisso $compromisso): RedirectResponse
    {
        $this->authorize('update', $compromisso);

        $dados = $request->validate([
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'data_conclusao' => ['nullable', 'date'],
            'criar_custo' => ['nullable', 'boolean'],
        ]);

        $resultado = $this->servico->concluir(
            $compromisso,
            $dados['valor_pago'] ?? null,
            $dados['data_conclusao'] ?? null,
            $dados['criar_custo'] ?? true
        );

        $msg = 'Compromisso marcado como concluído.';

        if ($resultado['custo'] !== null) {
            $msg .= ' Custo de '.number_format((float) $resultado['custo']->valor, 2, ',', ' ').' € registado.';
        }

        if ($resultado['proxima'] !== null) {
            $msg .= ' Próxima ocorrência a '.$resultado['proxima']->data->format('d/m/Y').'.';
        }

        return back()->with('success', $msg);
    }

    public function reabrir(Compromisso $compromisso): RedirectResponse
    {
        $this->authorize('update', $compromisso);

        $this->servico->reabrir($compromisso);

        return back()->with('success', 'Compromisso reaberto.');
    }

    public function destroy(Request $request, Compromisso $compromisso): RedirectResponse
    {
        $this->authorize('delete', $compromisso);

        $apagarSerie = $request->boolean('serie');

        if ($apagarSerie && $compromisso->compromisso_pai_id === null) {
            $compromisso->ocorrencias()->where('estado', 'pendente')->delete();
        }

        $compromisso->delete();

        return back()->with('success', $apagarSerie ? 'Série eliminada.' : 'Compromisso eliminado.');
    }

    private function consultaFiltrada(array $filtros)
    {
        return Compromisso::query()
            ->with(['campanha.cultura:id,nome', 'parcela:id,nome', 'maquina:id,nome', 'funcionario:id,nome'])
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('estado', $e))
            ->when($filtros['search'] ?? null, fn ($q, $s) => $q->where(function ($sub) use ($s): void {
                $sub->where('titulo', 'like', "%{$s}%")
                    ->orWhere('tipo', 'like', "%{$s}%")
                    ->orWhere('entidade', 'like', "%{$s}%");
            }));
    }

    private function formatarLista($colecao): array
    {
        return $colecao->map(fn (Compromisso $c) => [
            'id' => $c->id,
            'titulo' => $c->titulo,
            'descricao' => $c->descricao,
            'categoria' => $c->categoria,
            'tipo' => $c->tipo,
            'entidade' => $c->entidade,
            'data' => $c->data?->toDateString(),
            'hora' => $c->hora,
            'valor' => $c->valor,
            'estado' => $c->estado,
            'data_conclusao' => $c->data_conclusao?->toDateString(),
            'valor_pago' => $c->valor_pago,
            'recorrencia' => $c->recorrencia,
            'recorrencia_intervalo' => $c->recorrencia_intervalo,
            'recorrencia_unidade' => $c->recorrencia_unidade,
            'recorrencia_fim' => $c->recorrencia_fim?->toDateString(),
            'compromisso_pai_id' => $c->compromisso_pai_id,
            'antecedencia_aviso_dias' => $c->antecedencia_aviso_dias,
            'campanha_id' => $c->campanha_id,
            'parcela_id' => $c->parcela_id,
            'cultura_id' => $c->cultura_id,
            'maquina_id' => $c->maquina_id,
            'funcionario_id' => $c->funcionario_id,
            'custo_id' => $c->custo_id,
            'notas' => $c->notas,
            'atrasado' => $c->atrasado,
            'dias_para_prazo' => $c->dias_para_prazo,
            'contexto' => array_values(array_filter([
                $c->parcela?->nome,
                $c->maquina?->nome,
                $c->funcionario?->nome,
            ])),
        ])->values()->all();
    }
}
