<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TesourariaTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolve_resumo_de_entradas_saidas_saldo_e_tipos(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $campanha = $this->criarCampanha('Milho', 2026);

        Receita::query()->create([
            'descricao' => 'Venda de milho',
            'tipo' => 'venda_colheita',
            'valor' => 8450,
            'data' => '2026-10-20',
            'campanha_id' => $campanha->id,
        ]);
        Receita::query()->create([
            'descricao' => 'Subsidio PAC',
            'tipo' => 'subsidio',
            'valor' => 3200,
            'data' => '2026-12-05',
            'campanha_id' => $campanha->id,
        ]);
        Custo::query()->create([
            'descricao' => 'Gasoleo agricola',
            'tipo' => 'energia',
            'valor' => 148.50,
            'data_custo' => '2026-07-15',
            'campanha_id' => $campanha->id,
        ]);
        Custo::query()->create([
            'descricao' => 'Jornada de mao de obra',
            'tipo' => 'mao_obra',
            'valor' => 90,
            'data_custo' => '2026-07-15',
            'campanha_id' => $campanha->id,
        ]);

        $response = $this->getJson('/api/v1/tesouraria');

        $response->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.entradas', 11650)
            ->assertJsonPath('dados.saidas', 238.5)
            ->assertJsonPath('dados.saldo', 11411.5)
            ->assertJsonPath('dados.por_tipo_entrada.venda_colheita', 8450)
            ->assertJsonPath('dados.por_tipo_entrada.subsidio', 3200)
            ->assertJsonPath('dados.por_tipo_saida.energia', 148.5)
            ->assertJsonPath('dados.por_tipo_saida.mao_obra', 90);
    }

    public function test_filtra_por_campanha_por_nome(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $milho = $this->criarCampanha('Milho', 2026);
        $tomate = $this->criarCampanha('Tomate', 2026);

        Receita::query()->create([
            'descricao' => 'Venda milho',
            'tipo' => 'venda_colheita',
            'valor' => 1000,
            'data' => '2026-10-20',
            'campanha_id' => $milho->id,
        ]);
        Receita::query()->create([
            'descricao' => 'Venda tomate',
            'tipo' => 'venda_colheita',
            'valor' => 5000,
            'data' => '2026-08-20',
            'campanha_id' => $tomate->id,
        ]);
        Custo::query()->create([
            'descricao' => 'Custo milho',
            'tipo' => 'material',
            'valor' => 200,
            'data_custo' => '2026-07-15',
            'campanha_id' => $milho->id,
        ]);

        $response = $this->getJson('/api/v1/tesouraria?campanha=Milho%202026');

        $response->assertOk()
            ->assertJsonPath('dados.entradas', 1000)
            ->assertJsonPath('dados.saidas', 200)
            ->assertJsonPath('dados.saldo', 800);
    }

    public function test_filtra_por_intervalo_de_datas(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Receita::query()->create([
            'descricao' => 'Entrada antes',
            'tipo' => 'outro',
            'valor' => 100,
            'data' => '2026-01-10',
        ]);
        Receita::query()->create([
            'descricao' => 'Entrada dentro',
            'tipo' => 'servico',
            'valor' => 300,
            'data' => '2026-05-10',
        ]);
        Custo::query()->create([
            'descricao' => 'Saida dentro',
            'tipo' => 'energia',
            'valor' => 50,
            'data_custo' => '2026-05-12',
        ]);

        $response = $this->getJson('/api/v1/tesouraria?de=2026-05-01&ate=2026-05-31');

        $response->assertOk()
            ->assertJsonPath('dados.entradas', 300)
            ->assertJsonPath('dados.saidas', 50)
            ->assertJsonPath('dados.saldo', 250);
    }

    public function test_campanha_inexistente_devolve_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/tesouraria?campanha=Fantasma%202026');

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment(['Referencia de campanha nao encontrada: Fantasma 2026.']);
    }

    private function criarCampanha(string $culturaNome, int $ano): Campanha
    {
        $terreno = Terreno::query()->create([
            'nome' => "Terreno {$culturaNome}",
            'area_total' => 10,
        ]);

        $parcela = Parcela::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => "Parcela {$culturaNome}",
            'area_total' => 10,
        ]);

        $cultura = Cultura::query()->create([
            'parcela_id' => $parcela->id,
            'nome' => $culturaNome,
            'tipo' => 'cereal',
            'data_plantacao' => '2026-03-01',
        ]);

        return Campanha::query()->create([
            'cultura_id' => $cultura->id,
            'ano' => $ano,
            'data_inicio' => '2026-03-01',
        ]);
    }
}
