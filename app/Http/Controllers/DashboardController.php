<?php

namespace App\Http\Controllers;

use App\Models\Alfaia;
use App\Models\Cultura;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $this->buildStats(),
            'statusCards' => $this->buildStatusCards(),
            'recentOperations' => $this->buildRecentOperations(),
            'focusAreas' => $this->buildFocusAreas(),
            'mapPolygons' => $this->buildMapPolygons(),
        ]);
    }

    private function buildStats(): array
    {
        return [
            [
                'label' => 'Terrenos',
                'value' => $this->safeCount('terrenos', Terreno::class),
                'description' => 'Unidades produtivas registadas',
            ],
            [
                'label' => 'Parcelas',
                'value' => $this->safeCount('parcelas', Parcela::class),
                'description' => 'Divisões prontas para planeamento',
            ],
            [
                'label' => 'Culturas',
                'value' => $this->safeCount('culturas', Cultura::class),
                'description' => 'Culturas com acompanhamento ativo',
            ],
            [
                'label' => 'Operações',
                'value' => $this->safeCount('operacoes', Operacao::class),
                'description' => 'Registos operacionais acumulados',
            ],
        ];
    }

    private function buildStatusCards(): array
    {
        return [
            [
                'label' => 'Máquinas ativas',
                'value' => $this->safeWhereCount('maquinas', Maquina::class, 'estado', 'ativo'),
                'total' => $this->safeCount('maquinas', Maquina::class),
                'tone' => 'emerald',
            ],
            [
                'label' => 'Alfaias operacionais',
                'value' => $this->safeWhereCount('alfaias', Alfaia::class, 'estado', 'ativo'),
                'total' => $this->safeCount('alfaias', Alfaia::class),
                'tone' => 'amber',
            ],
            [
                'label' => 'Operações pendentes',
                'value' => $this->safeWhereCount('operacoes', Operacao::class, 'estado', 'pendente'),
                'total' => $this->safeCount('operacoes', Operacao::class),
                'tone' => 'sky',
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
            ->limit(5)
            ->get()
            ->map(function (Operacao $operacao) {
                return [
                    'id' => $operacao->id,
                    'tipo' => $operacao->tipo,
                    'estado' => $operacao->estado,
                    'inicio' => optional($operacao->data_hora_inicio)?->format('d/m/Y H:i'),
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
                'title' => 'Planeamento agrícola',
                'description' => 'Organize terrenos, parcelas e culturas com base no ciclo produtivo.',
            ],
            [
                'title' => 'Execução no terreno',
                'description' => 'Acompanhe operações, máquinas e equipas sem depender de folhas soltas.',
            ],
            [
                'title' => 'Próxima fase',
                'description' => 'A base está pronta para avançar com colheitas, stock e relatórios.',
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
                        'extra' => trim(($parcela->terreno?->nome ? "Terreno: {$parcela->terreno->nome}" : '').($parcela->tipo_ocupacao ? " | {$parcela->tipo_ocupacao}" : '').($parcela->numero_arvores ? " | {$parcela->numero_arvores} arvores" : '')),
                        'poligono' => $parcela->poligono,
                    ])
                    ->all()
            );
        }

        return $polygons;
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
}
