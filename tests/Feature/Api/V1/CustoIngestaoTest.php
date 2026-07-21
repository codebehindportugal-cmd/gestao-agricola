<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Parcela;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustoIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_insere_um_custo_simples(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/custos', [
            'descricao' => 'Gasoleo agricola',
            'tipo' => 'energia',
            'valor' => 148.50,
            'data' => '2026-07-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.criados.0', 1)
            ->assertJsonPath('dados.custos.0.data', '2026-07-15');

        $this->assertDatabaseHas('custos', [
            'descricao' => 'Gasoleo agricola',
            'tipo' => 'energia',
            'valor' => 148.50,
        ]);
    }

    public function test_insere_um_lote_de_custos(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/custos', [
            'custos' => [
                [
                    'descricao' => 'Gasoleo agricola',
                    'tipo' => 'energia',
                    'valor' => 148.50,
                    'data' => '2026-07-15',
                ],
                [
                    'descricao' => 'Jornada de mao de obra',
                    'tipo' => 'mao_obra',
                    'valor' => 90,
                    'data' => '2026-07-15',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(2, 'dados.criados');

        $this->assertDatabaseCount('custos', 2);
    }

    public function test_tipo_invalido_devolve_422_com_valores_aceites(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/custos', [
            'descricao' => 'Custo estranho',
            'tipo' => 'combustivel',
            'valor' => 10,
            'data' => '2026-07-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment([
                'Tipo invalido. Valores aceites: material, mao_obra, maquinaria, energia, manutencao, outro.',
            ]);
    }

    public function test_idempotencia_nao_duplica_referencia_externa(): void
    {
        $this->autenticarApi();

        Custo::query()->create([
            'descricao' => 'Gasoleo agricola',
            'tipo' => 'energia',
            'valor' => 148.50,
            'data_custo' => '2026-07-15',
            'referencia_externa' => 'fatura-2026-0842',
        ]);

        $response = $this->postJson('/api/v1/custos', [
            'descricao' => 'Gasoleo agricola duplicado',
            'tipo' => 'energia',
            'valor' => 200,
            'data' => '2026-07-16',
            'referencia_externa' => 'fatura-2026-0842',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.criados', [])
            ->assertJsonPath('dados.ignorados.0.referencia_externa', 'fatura-2026-0842')
            ->assertJsonFragment(['custo ja registado (fatura-2026-0842)']);

        $this->assertDatabaseCount('custos', 1);
    }

    public function test_ligacao_por_nome_a_campanha_e_resolvida(): void
    {
        $this->autenticarApi();
        $campanha = $this->criarCampanha('Milho', 2026);

        $response = $this->postJson('/api/v1/custos', [
            'descricao' => 'Gasoleo agricola',
            'tipo' => 'energia',
            'valor' => 148.50,
            'data' => '2026-07-15',
            'campanha' => 'Milho 2026',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.custos.0.campanha.id', $campanha->id);

        $this->assertDatabaseHas('custos', [
            'campanha_id' => $campanha->id,
        ]);
    }

    public function test_ligacao_por_nome_inexistente_devolve_erro_claro_e_nao_grava_lote(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/custos', [
            'custos' => [
                [
                    'descricao' => 'Valido',
                    'tipo' => 'energia',
                    'valor' => 10,
                    'data' => '2026-07-15',
                ],
                [
                    'descricao' => 'Invalido',
                    'tipo' => 'energia',
                    'valor' => 20,
                    'data' => '2026-07-15',
                    'campanha' => 'Campanha Fantasma 2026',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment(['Referencia de campanha nao encontrada: Campanha Fantasma 2026.']);

        $this->assertDatabaseCount('custos', 0);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['custos:write']);

        return $user;
    }

    private function criarCampanha(string $culturaNome, int $ano): Campanha
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
