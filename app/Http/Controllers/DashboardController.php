<?php

namespace App\Http\Controllers;

use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Compromisso;
use App\Models\Cultura;
use App\Models\Despesa;
use App\Models\Maquina;
use App\Models\Manutencao;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\Terreno;
use App\Services\ResumoPorEspecieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ResumoPorEspecieService $resumoEspecies): Response
    {
        $campanha = $this->campanhaEmFoco($request);
        $resumo = $this->buildResumoCampanha($campanha, $resumoEspecies);

        return Inertia::render('Dashboard', [
            'resumoCampanha' => $resumo,
            'atencao' => $this->buildAtencao($campanha, $resumo),
            'proximosPagamentos' => $this->buildProximosPagamentos(),
            'stats' => $this->buildStats(),
            'statusCards' => $this->buildStatusCards(),
            'recentOperations' => $this->buildRecentOperations(),
            'focusAreas' => $this->buildFocusAreas(),
            'mapPolygons' => $this->buildMapPolygons(),
            'alertas' => $this->buildAlertas(),
            'despesasMes' => $this->buildDespesasMes(),
        ]);
    }

    private function buildStats(): array
    {
        return [
            [
                'label' => 'Parcelas',
                'value' => $this->safeCount('parcelas', Parcela::class),
                'description' => 'Base física pronta para registar operações.',
            ],
            [
                'label' => 'Culturas',
                'value' => $this->safeCount('culturas', Cultura::class),
                'description' => 'Culturas associadas às parcelas.',
            ],
            [
                'label' => 'Campanhas',
                'value' => $this->safeCount('campanhas', Campanha::class),
                'description' => 'Ano agrícola com custos e produção.',
            ],
            [
                'label' => 'Operações',
                'value' => $this->safeCount('operacoes', Operacao::class),
                'description' => 'Registos do caderno de campo.',
            ],
        ];
    }

    private function buildStatusCards(): array
    {
        return [
            [
                'label' => 'Operações planeadas',
                'value' => $this->safeWhereCount('operacoes', Operacao::class, 'estado', 'planejada'),
                'total' => $this->safeCount('operacoes', Operacao::class),
                'tone' => 'sky',
            ],
            [
                'label' => 'Máquinas ativas',
                'value' => $this->safeWhereCount('maquinas', Maquina::class, 'estado', 'ativo'),
                'total' => $this->safeCount('maquinas', Maquina::class),
                'tone' => 'emerald',
            ],
            [
                'label' => 'Alfaias ativas',
                'value' => $this->safeWhereCount('alfaias', Alfaia::class, 'estado', 'ativo'),
                'total' => $this->safeCount('alfaias', Alfaia::class),
                'tone' => 'amber',
            ],
        ];
    }

    private function buildRecentOperations(): array
    {
        if (! Schema::hasTable('operacoes')) {
            return [];
        }

        return Operacao::query()
            ->with(['parcela:id,nome', 'cultura:id,nome', 'maquina:id,nome'])
            ->latest('data_hora_inicio')
            ->limit(80)
            ->get()
            ->map(function (Operacao $operacao) {
                return [
                    'id' => $operacao->id,
                    'tipo' => $operacao->tipo,
                    'estado' => $operacao->estado,
                    'inicio' => optional($operacao->data_hora_inicio)?->format('d/m/Y H:i'),
                    'dia' => optional($operacao->data_hora_inicio)?->format('Y-m-d'),
                    'parcela' => $operacao->parcela?->nome ?? 'Sem parcela',
                    'cultura' => $operacao->cultura?->nome ?? 'Sem cultura',
                    'maquina' => $operacao->maquina?->nome ?? 'Sem máquina',
                ];
            })
            ->all();
    }

    private function buildFocusAreas(): array
    {
        return [
            [
                'title' => 'Registar trabalho diário',
                'description' => 'Entrar rapidamente em operações com datas, recursos, produtos e observações.',
            ],
            [
                'title' => 'Fechar o caderno de campo',
                'description' => 'Guardar tratamentos fitossanitários com os dados DGAV necessários para certificação.',
            ],
            [
                'title' => 'Perceber custos',
                'description' => 'Ligar operação, campanha e produtos para calcular custo real por campanha.',
            ],
        ];
    }

    private function buildMapPolygons(): array
    {
        $polygons = [];

        if (Schema::hasTable('terrenos') && Schema::hasColumn('terrenos', 'poligono')) {
            $polygons = array_merge(
                $polygons,
                Terreno::query()
                    ->whereNotNull('poligono')
                    ->get(['id', 'nome', 'area_total', 'estado', 'poligono'])
                    ->map(fn (Terreno $terreno) => [
                        'id' => "terreno-{$terreno->id}",
                        'tipo' => 'terreno',
                        'nome' => $terreno->nome,
                        'area_total' => $terreno->area_total,
                        'extra' => "Estado: {$terreno->estado}",
                        'poligono' => $terreno->poligono,
                    ])
                    ->all()
            );
        }

        if (Schema::hasTable('parcelas') && Schema::hasColumn('parcelas', 'poligono')) {
            $polygons = array_merge(
                $polygons,
                Parcela::query()
                    ->with('terreno:id,nome')
                    ->whereNotNull('poligono')
                    ->get(['id', 'terreno_id', 'nome', 'area_total', 'tipo_ocupacao', 'numero_arvores', 'poligono'])
                    ->map(fn (Parcela $parcela) => [
                        'id' => "parcela-{$parcela->id}",
                        'tipo' => 'parcela',
                        'nome' => $parcela->nome,
                        'area_total' => $parcela->area_total,
                        'extra' => trim(
                            ($parcela->terreno?->nome ? "Terreno: {$parcela->terreno->nome}" : '') .
                            ($parcela->tipo_ocupacao ? " | {$parcela->tipo_ocupacao}" : '') .
                            ($parcela->numero_arvores ? " | {$parcela->numero_arvores} árvores" : '')
                        ),
                        'poligono' => $parcela->poligono,
                    ])
                    ->all()
            );
        }

        return $polygons;
    }

    private function buildAlertas(): array
    {
        $hoje = now()->startOfDay();
        $alertasIS = [];

        if (Schema::hasTable('operacoes') && Schema::hasTable('operacao_produtos')) {
            $operacoes = Operacao::query()
                ->with([
                    'parcela:id,nome',
                    'cultura:id,nome',
                    'produtos:id,nome',
                ])
                ->where('tipo', 'tratamento fitossanitario')
                ->whereNotNull('data_hora_inicio')
                ->where('data_hora_inicio', '>=', $hoje->copy()->subDays(90))
                ->whereHas('produtos', fn ($q) => $q->whereNotNull('intervalo_seguranca_dias')->where('intervalo_seguranca_dias', '>', 0))
                ->get();

            foreach ($operacoes as $operacao) {
                foreach ($operacao->produtos as $produto) {
                    $is = (int) $produto->pivot->intervalo_seguranca_dias;

                    if ($is <= 0) {
                        continue;
                    }

                    $fimIntervalo = $operacao->data_hora_inicio->copy()->addDays($is)->startOfDay();
                    $diasRestantes = (int) $hoje->diffInDays($fimIntervalo, false);

                    if ($diasRestantes < 0) {
                        continue;
                    }

                    $alertasIS[] = [
                        'operacao_id' => $operacao->id,
                        'parcela_nome' => $operacao->parcela?->nome ?? 'Parcela desconhecida',
                        'cultura_nome' => $operacao->cultura?->nome,
                        'produto_nome' => $produto->nome,
                        'data_aplicacao' => $operacao->data_hora_inicio->format('d/m/Y'),
                        'fim_intervalo' => $fimIntervalo->format('d/m/Y'),
                        'dias_restantes' => $diasRestantes,
                    ];
                }
            }

            usort($alertasIS, fn ($a, $b) => $a['dias_restantes'] <=> $b['dias_restantes']);
            $alertasIS = array_slice($alertasIS, 0, 10);
        }

        $alertasManutencao = [];

        if (Schema::hasTable('manutencoes')) {
            $alertasManutencao = Manutencao::query()
                ->with('maquina:id,nome')
                ->whereNotNull('proxima_manutencao')
                ->where('proxima_manutencao', '<=', $hoje->copy()->addDays(30)->toDateString())
                ->orderBy('proxima_manutencao')
                ->limit(10)
                ->get()
                ->map(fn (Manutencao $m) => [
                    'maquina_nome' => $m->maquina?->nome ?? 'Máquina desconhecida',
                    'tipo' => $m->tipo,
                    'proxima_manutencao' => optional($m->proxima_manutencao)->format('d/m/Y'),
                    'dias_ate_manutencao' => (int) $hoje->diffInDays($m->proxima_manutencao, false),
                ])
                ->values()
                ->all();
        }

        return [
            'intervalo_seguranca' => $alertasIS,
            'manutencoes' => $alertasManutencao,
        ];
    }

    private function buildDespesasMes(): array
    {
        if (! Schema::hasTable('despesas')) {
            return ['total' => 0, 'count' => 0, 'variacao' => null, 'por_categoria' => []];
        }

        $mes = now()->month;
        $ano = now()->year;

        $despesasMes = Despesa::query()
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->get(['valor', 'categoria']);

        $total = (float) $despesasMes->sum('valor');

        $mesPrev = $mes === 1 ? 12 : $mes - 1;
        $anoPrev = $mes === 1 ? $ano - 1 : $ano;
        $totalAnterior = (float) Despesa::query()
            ->whereYear('data', $anoPrev)
            ->whereMonth('data', $mesPrev)
            ->sum('valor');

        $variacao = $totalAnterior > 0
            ? round((($total - $totalAnterior) / $totalAnterior) * 100, 1)
            : null;

        $porCategoria = $despesasMes->groupBy('categoria')
            ->map(fn ($group) => (float) $group->sum('valor'))
            ->sortDesc()
            ->take(3)
            ->all();

        return [
            'total' => $total,
            'count' => $despesasMes->count(),
            'variacao' => $variacao,
            'por_categoria' => $porCategoria,
            'mes' => $mes,
            'ano' => $ano,
        ];
    }

    private function safeCount(string $table, string $model): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return $model::query()->count();
    }

    private function safeWhereCount(string $table, string $model, string $column, string $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return $model::query()->where($column, $value)->count();
    }

    /**
     * A campanha que o utilizador escolheu no menu (ano guardado na sessao);
     * dentro desse ano, a que esta em curso.
     */
    private function campanhaEmFoco(Request $request): ?Campanha
    {
        if (! Schema::hasTable('campanhas')) {
            return null;
        }

        $ano = $request->session()->get('campanha_ativa_ano');
        $query = Campanha::query();

        if ($ano) {
            $query->where('ano', (int) $ano);
        }

        return $query->orderByRaw("status = 'em_curso' desc")->orderByDesc('data_inicio')->orderByDesc('id')->first()
            ?? Campanha::query()->orderByDesc('ano')->orderByDesc('id')->first();
    }

    private function buildResumoCampanha(?Campanha $campanha, ResumoPorEspecieService $servico): ?array
    {
        if (! $campanha) {
            return null;
        }

        try {
            $resumo = $servico->paraCampanha($campanha);
        } catch (\Throwable $erro) {
            report($erro);

            return null;
        }

        $total = $resumo['total'] ?? [];

        return [
            'id' => $campanha->id,
            'nome' => $resumo['campanha']['nome'] ?? $campanha->nome,
            'fim' => $campanha->data_fim?->format('d/m/Y'),
            'terminou' => $campanha->data_fim !== null && $campanha->data_fim->lt(now()->startOfDay()),
            'kg' => (float) ($total['kg'] ?? 0),
            'kg_vendidos' => (float) ($total['kg_vendidos'] ?? 0),
            'vendas' => (float) ($total['vendas'] ?? 0),
            'custo_total' => (float) ($total['custo_total'] ?? 0),
            'margem' => (float) ($total['margem'] ?? 0),
            'custo_kg' => $total['custo_kg'] ?? null,
            'preco_medio_kg' => $total['preco_medio_kg'] ?? null,
            'especies' => collect($resumo['especies'] ?? [])
                ->map(fn (array $linha) => [
                    'especie' => $linha['especie'],
                    'area_ha' => (float) ($linha['area_ha'] ?? 0),
                    'kg' => (float) ($linha['kg'] ?? 0),
                    'kg_vendidos' => (float) ($linha['kg_vendidos'] ?? 0),
                    'vendas' => (float) ($linha['vendas'] ?? 0),
                    'custo_total' => (float) ($linha['custo_total'] ?? 0),
                    'custo_kg' => $linha['custo_kg'] ?? null,
                    'margem' => (float) ($linha['margem'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * O que precisa de uma mao: cada entrada diz o problema e para onde ir.
     */
    private function buildAtencao(?Campanha $campanha, ?array $resumo): array
    {
        $itens = [];

        foreach ($resumo['especies'] ?? [] as $linha) {
            if ($linha['especie'] === ResumoPorEspecieService::SEM_ESPECIE || $linha['kg'] <= 0 || $linha['kg_vendidos'] > 0) {
                continue;
            }

            $kg = number_format($linha['kg'], 0, ',', ' ');
            $itens[] = [
                'titulo' => "Vendas de {$this->minuscula($linha['especie'])} por registar",
                'texto' => "{$kg} kg colhidos e nenhuma venda ligada. Sem isto a margem aparece negativa.",
                'acao' => 'Ver custos e vendas',
                'href' => route('app.campanhas.index'),
                'tom' => 'aviso',
            ];
        }

        $semEspecie = collect($resumo['especies'] ?? [])->firstWhere('especie', ResumoPorEspecieService::SEM_ESPECIE);
        if ($semEspecie && $semEspecie['kg_vendidos'] > 0) {
            $kg = number_format($semEspecie['kg_vendidos'], 0, ',', ' ');
            $itens[] = [
                'titulo' => 'Vendas sem espécie',
                'texto' => "{$kg} kg vendidos não dizem se são pera ou maçã, por isso não entram na margem de cada uma.",
                'acao' => 'Corrigir vendas',
                'href' => route('app.campanhas.index'),
                'tom' => 'aviso',
            ];
        }

        if (Schema::hasTable('parcelas')) {
            $semArea = Parcela::query()
                ->where(fn ($query) => $query->whereNull('area_util')->orWhere('area_util', '<=', 0))
                ->where(fn ($query) => $query->whereNull('area_total')->orWhere('area_total', '<=', 0))
                ->orderBy('nome')
                ->pluck('nome');

            if ($semArea->isNotEmpty()) {
                $itens[] = [
                    'titulo' => $semArea->count() === 1 ? '1 parcela sem área' : "{$semArea->count()} parcelas sem área",
                    'texto' => $semArea->take(4)->implode(', ').'. As doses por hectare e o rateio precisam dela.',
                    'acao' => 'Abrir parcelas',
                    'href' => route('app.parcelas.index'),
                    'tom' => 'info',
                ];
            }
        }

        if ($campanha && $campanha->data_fim && $campanha->data_fim->lt(now()->startOfDay())
            && ! Campanha::query()->whereDate('data_inicio', '>', $campanha->data_fim)->exists()) {
            $itens[] = [
                'titulo' => 'A campanha terminou a '.$campanha->data_fim->format('d/m'),
                'texto' => 'Abre a campanha seguinte para os novos registos não caírem nesta.',
                'acao' => 'Abrir campanha',
                'href' => route('app.campanhas.index'),
                'tom' => 'info',
            ];
        }

        return $itens;
    }

    private function buildProximosPagamentos(): array
    {
        if (! Schema::hasTable('compromissos')) {
            return [];
        }

        $hoje = now()->startOfDay();

        return Compromisso::query()
            ->where('estado', 'pendente')
            ->whereDate('data', '<=', $hoje->copy()->addDays(45))
            ->orderBy('data')
            ->limit(6)
            ->get(['id', 'titulo', 'categoria', 'tipo', 'entidade', 'data', 'valor'])
            ->map(function (Compromisso $compromisso) use ($hoje) {
                $data = \Illuminate\Support\Carbon::parse($compromisso->data)->startOfDay();

                return [
                    'id' => $compromisso->id,
                    'titulo' => $compromisso->titulo,
                    'detalhe' => $compromisso->entidade ?: ($compromisso->tipo ?: ucfirst(str_replace('_', ' ', (string) $compromisso->categoria))),
                    'dia' => $data->format('d'),
                    'mes' => mb_substr($data->locale('pt')->translatedFormat('M'), 0, 3),
                    'dias' => (int) $hoje->diffInDays($data, false),
                    'valor' => $compromisso->valor !== null ? (float) $compromisso->valor : null,
                ];
            })
            ->all();
    }

    private function minuscula(string $texto): string
    {
        return [
            'Pereira' => 'pera',
            'Macieira' => 'maçã',
            'Pessegueiro' => 'pêssego',
        ][$texto] ?? mb_strtolower($texto);
    }
}
