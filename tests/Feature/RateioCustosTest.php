<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use App\Services\RateioCustosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A luz das regas e o frio das camaras nao pertencem a uma campanha: pagam-se
 * de uma vez e servem todas ao mesmo tempo. Estes testes fixam como e que
 * esse dinheiro chega ao custo/kg de cada uma.
 */
class RateioCustosTest extends TestCase
{
    use RefreshDatabase;

    public function test_custo_partilhado_reparte_se_pelos_quilos_colhidos(): void
    {
        $pereiras = $this->campanha('Pereiras 2026', 'Cumeira', 2, 30000);
        $macas = $this->campanha('Macas 2026', 'Casa', 1, 10000);

        Custo::query()->create([
            'descricao' => 'Eletricidade da rega - Julho',
            'tipo' => 'energia',
            'valor' => 400,
            'data_custo' => '2026-07-31',
            'rateavel' => true,
            'base_rateio' => 'kg',
        ]);

        // 30 000 kg contra 10 000 kg: tres quartos para as pereiras.
        $this->assertSame(300.0, $pereiras->fresh()->custo_rateado);
        $this->assertSame(100.0, $macas->fresh()->custo_rateado);
    }

    public function test_sem_colheitas_reparte_se_pela_area(): void
    {
        $grande = $this->campanha('Pereiras 2026', 'Cumeira', 3);
        $pequena = $this->campanha('Macas 2026', 'Casa', 1);

        Custo::query()->create([
            'descricao' => 'Seguro de colheita',
            'tipo' => 'outro',
            'valor' => 200,
            'data_custo' => '2026-02-10',
            'rateavel' => true,
            'base_rateio' => 'kg',
        ]);

        $this->assertSame(150.0, $grande->fresh()->custo_rateado);
        $this->assertSame(50.0, $pequena->fresh()->custo_rateado);
    }

    public function test_a_soma_das_quotas_bate_certo_com_a_fatura(): void
    {
        $a = $this->campanha('Pereiras 2026', 'Cumeira', 1, 1);
        $b = $this->campanha('Macas 2026', 'Casa', 1, 1);
        $c = $this->campanha('Ameixas 2026', 'Buga', 1, 1);

        Custo::query()->create([
            'descricao' => 'Camaras frigorificas - Agosto',
            'tipo' => 'energia',
            'valor' => 100,
            'data_custo' => '2026-08-31',
            'rateavel' => true,
            'base_rateio' => 'kg',
        ]);

        $total = $a->fresh()->custo_rateado + $b->fresh()->custo_rateado + $c->fresh()->custo_rateado;

        $this->assertSame(100.0, round($total, 2));
    }

    public function test_custo_fora_do_periodo_da_campanha_nao_entra(): void
    {
        $campanha = $this->campanha('Pereiras 2026', 'Cumeira', 1, 1000);

        Custo::query()->create([
            'descricao' => 'Eletricidade de 2024',
            'tipo' => 'energia',
            'valor' => 500,
            'data_custo' => '2024-05-31',
            'rateavel' => true,
            'base_rateio' => 'kg',
        ]);

        $this->assertSame(0.0, $campanha->fresh()->custo_rateado);
    }

    public function test_o_partilhado_entra_no_custo_por_kg_da_campanha(): void
    {
        $this->autenticar();
        $campanha = $this->campanha('Pereiras 2026', 'Cumeira', 1, 1000);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'descricao' => 'Adubo',
            'tipo' => 'material',
            'valor' => 500,
            'data_custo' => '2026-03-10',
        ]);

        Custo::query()->create([
            'descricao' => 'Eletricidade da rega',
            'tipo' => 'energia',
            'valor' => 500,
            'data_custo' => '2026-07-31',
            'rateavel' => true,
            'base_rateio' => 'kg',
        ]);

        $this->get(route('app.campanhas.show', $campanha))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resumo.custo_diretos', 500)
                ->where('resumo.custo_rateado', 500)
                ->where('resumo.custo_total', 1000)
                ->where('resumo.custo_por_kg', 1)
                ->count('rateio', 1));
    }

    public function test_venda_com_quilos_da_preco_medio_e_margem(): void
    {
        $this->autenticar();
        $campanha = $this->campanha('Pereiras 2026', 'Cumeira', 1, 1000);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'descricao' => 'Adubo',
            'tipo' => 'material',
            'valor' => 300,
            'data_custo' => '2026-03-10',
        ]);

        Receita::query()->create([
            'campanha_id' => $campanha->id,
            'descricao' => 'Venda de pera rocha',
            'tipo' => 'venda_colheita',
            'valor' => 450,
            'quantidade' => 1000,
            'unidade' => 'kg',
            'preco_unitario' => 0.45,
            'data' => '2026-09-05',
        ]);

        $this->get(route('app.campanhas.show', $campanha))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resumo.receita_total', 450)
                ->where('resumo.quantidade_vendida', 1000)
                ->where('resumo.preco_medio_venda', 0.45)
                ->where('resumo.margem', 150));
    }

    public function test_ecra_de_despesas_regista_venda_por_quilo_e_custo_partilhado(): void
    {
        $this->autenticar();
        $campanha = $this->campanha('Pereiras 2026', 'Cumeira', 1, 1000);
        session(['campanha_ativa_id' => $campanha->id, 'campanha_ativa_ano' => 2026]);

        // Sem valor: sao os quilos vezes o preco que o dao.
        $this->post(route('app.despesas.vendas.store'), [
            'descricao' => 'Venda de pera rocha',
            'tipo' => 'venda_colheita',
            'quantidade' => 800,
            'preco_unitario' => 0.5,
            'data' => '2026-09-05',
        ])->assertRedirect();

        $venda = Receita::query()->firstOrFail();
        $this->assertSame('400.00', (string) $venda->valor);
        $this->assertSame('kg', $venda->unidade);

        $this->post(route('app.despesas.partilhados.store'), [
            'descricao' => 'Eletricidade da rega',
            'tipo' => 'energia',
            'valor' => 120,
            'data_custo' => '2026-07-31',
            'base_rateio' => 'kg',
        ])->assertRedirect();

        $custo = Custo::query()->partilhados()->firstOrFail();
        $this->assertTrue($custo->rateavel);
        $this->assertNull($custo->campanha_id);

        app(RateioCustosService::class)->esquecer();
        $this->assertSame(120.0, $campanha->fresh()->custo_rateado);
    }

    private function campanha(string $nome, string $nomeParcela, float $area, ?float $kg = null): Campanha
    {
        $terreno = Terreno::query()->create(['nome' => $nomeParcela, 'area_total' => $area]);
        $parcela = Parcela::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => $nomeParcela,
            'area_total' => $area,
            'area_util' => $area,
        ]);

        $campanha = Campanha::query()->create([
            'nome' => $nome,
            'ano' => 2026,
            'data_inicio' => '2025-10-01',
            'data_fim' => '2026-09-30',
            'status' => 'em_curso',
        ]);
        $campanha->parcelas()->sync([$parcela->id]);

        if ($kg !== null) {
            $cultura = Cultura::query()->create([
                'parcela_id' => $parcela->id,
                'nome' => $nome,
                'tipo' => 'fruta',
                'data_plantacao' => '2020-01-01',
            ]);

            Colheita::query()->create([
                'campanha_id' => $campanha->id,
                'cultura_id' => $cultura->id,
                'parcela_id' => $parcela->id,
                'data_colheita' => '2026-08-20',
                'quantidade_total' => $kg,
                'qualidade' => 'comercial',
            ]);
        }

        return $campanha->fresh(['parcelas', 'colheitas']);
    }

    private function autenticar(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($role);

        $this->actingAs($user);

        return $user;
    }
}
