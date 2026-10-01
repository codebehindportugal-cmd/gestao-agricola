<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use App\Services\RelatorioCampanhaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O relatorio da campanha inteira: o mes a mes soma o mesmo que a campanha.
 */
class RelatorioCampanhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meses_e_rubricas_somam_o_custo_total_da_campanha(): void
    {
        $campanha = $this->cenario();

        $relatorio = app(RelatorioCampanhaService::class)->paraCampanha($campanha->fresh());

        // 12 meses, de outubro a setembro.
        $this->assertCount(12, $relatorio['por_mes']);
        $this->assertSame('2025-10', $relatorio['por_mes'][0]['mes']);
        $this->assertSame('2026-09', $relatorio['por_mes'][11]['mes']);

        $campanha = $campanha->fresh(['operacoes.produtos', 'custos', 'colheitas', 'receitas']);
        $this->assertSame($campanha->custo_total_calculado, $relatorio['total']['custos']);
        $this->assertSame(
            $relatorio['total']['custos'],
            round(array_sum(array_column($relatorio['por_mes'], 'custos')), 2)
        );

        // A operacao de 1000 € com 700 € de mao de obra ligada: 700 em mao de
        // obra e os 300 que faltam em "outro", no mes da operacao.
        $agosto = collect($relatorio['por_mes'])->firstWhere('mes', '2026-08');
        $this->assertSame(700.0, $agosto['rubricas']['mao_obra']);
        $this->assertSame(300.0, $agosto['rubricas']['outro']);
        $this->assertSame(10000.0, $agosto['kg_colhidos']);

        // A fatura de adubo de novembro fica em novembro.
        $novembro = collect($relatorio['por_mes'])->firstWhere('mes', '2025-11');
        $this->assertSame(250.0, $novembro['rubricas']['material']);

        $setembro = collect($relatorio['por_mes'])->firstWhere('mes', '2026-09');
        $this->assertSame(4000.0, $setembro['vendas']);

        $this->assertSame(1250.0, $relatorio['total']['custos']);
        $this->assertSame(4000.0, $relatorio['total']['vendas']);
        $this->assertSame(2750.0, $relatorio['total']['margem']);
        $this->assertSame(0.125, $relatorio['total']['custo_por_kg']);
        $this->assertSame(0.5, $relatorio['total']['preco_medio']);

        // Custos acumulados ao longo da campanha.
        $this->assertSame(1250.0, end($relatorio['por_mes'])['custos_acumulado']);
    }

    public function test_pagina_do_relatorio_responde(): void
    {
        $this->withoutVite();
        $campanha = $this->cenario();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));

        $this->actingAs($user)
            ->get(route('app.campanhas.relatorio', $campanha))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Campanhas/Relatorio')
                ->where('relatorio.total.custos', 1250)
                ->has('relatorio.por_mes', 12));
    }

    private function cenario(): Campanha
    {
        $terreno = Terreno::query()->create(['nome' => 'Cumeira', 'area_total' => 2]);
        $parcela = Parcela::query()->create(['terreno_id' => $terreno->id, 'nome' => 'Cumeira', 'area_total' => 2]);
        $cultura = Cultura::query()->create([
            'parcela_id' => $parcela->id, 'nome' => 'Cumeira', 'tipo' => 'Pereira', 'data_plantacao' => '2020-01-01',
        ]);

        $campanha = Campanha::query()->create([
            'nome' => '2025/2026', 'ano' => 2026,
            'data_inicio' => '2025-10-01', 'data_fim' => '2026-09-30', 'status' => 'em_curso',
        ]);
        $campanha->parcelas()->sync([$parcela->id]);

        $apanha = Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'cultura_id' => $cultura->id,
            'parcela_id' => $parcela->id,
            'tipo' => 'colheita',
            'data_hora_inicio' => '2026-08-15 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 1000,
        ]);

        Custo::query()->create([
            'descricao' => 'Apanha - mao de obra', 'tipo' => 'mao_obra', 'valor' => 700,
            'data_custo' => '2026-08-15', 'operacao_id' => $apanha->id, 'campanha_id' => $campanha->id,
        ]);

        Custo::query()->create([
            'descricao' => 'Fatura adubo', 'tipo' => 'material', 'valor' => 250,
            'data_custo' => '2025-11-20', 'campanha_id' => $campanha->id, 'cultura_id' => $cultura->id,
        ]);

        Colheita::query()->create([
            'campanha_id' => $campanha->id, 'cultura_id' => $cultura->id, 'parcela_id' => $parcela->id,
            'operacao_id' => $apanha->id, 'data_colheita' => '2026-08-23',
            'quantidade_total' => 10000, 'unidade_medida' => 'kg', 'qualidade' => 'comercial',
        ]);

        Receita::query()->create([
            'descricao' => 'Venda pera', 'tipo' => 'venda_colheita', 'valor' => 4000,
            'quantidade' => 8000, 'unidade' => 'kg', 'data' => '2026-09-10',
            'campanha_id' => $campanha->id, 'cultura_id' => $cultura->id,
        ]);

        return $campanha;
    }
}
