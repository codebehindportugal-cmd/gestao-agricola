<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Terreno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceitaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_receita_create_funciona_e_soma_total_da_campanha(): void
    {
        $campanha = $this->criarCampanha();

        Receita::query()->create([
            'descricao' => 'Venda de milho',
            'tipo' => 'venda_colheita',
            'valor' => 8450,
            'data' => '2026-10-20',
            'campanha_id' => $campanha->id,
            'comprador_nome' => 'Cooperativa Agricola',
            'documento' => 'FT 2026/338',
            'referencia_externa' => 'venda-2026-10-20-milho',
        ]);

        Receita::query()->create([
            'descricao' => 'Subsidio PAC',
            'tipo' => 'subsidio',
            'valor' => 3200,
            'data' => '2026-12-05',
            'campanha_id' => $campanha->id,
        ]);

        $this->assertDatabaseHas('receitas', [
            'descricao' => 'Venda de milho',
            'referencia_externa' => 'venda-2026-10-20-milho',
        ]);

        $this->assertSame(11650.0, $campanha->fresh()->receita_total);
    }

    private function criarCampanha(): Campanha
    {
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Norte',
            'area_total' => 10,
        ]);

        $parcela = Parcela::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Parcela Norte',
            'area_total' => 10,
        ]);

        $cultura = Cultura::query()->create([
            'parcela_id' => $parcela->id,
            'nome' => 'Milho',
            'tipo' => 'cereal',
            'data_plantacao' => '2026-03-01',
        ]);

        return Campanha::query()->create([
            'cultura_id' => $cultura->id,
            'ano' => 2026,
            'data_inicio' => '2026-03-01',
        ]);
    }
}
