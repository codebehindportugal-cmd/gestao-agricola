<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Lote;
use App\Models\Parcela;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ColheitaIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_colheita_com_dois_lotes(): void
    {
        $this->autenticarApi();
        $dados = $this->criarDadosBase();

        $response = $this->postJson('/api/v1/colheitas', [
            'campanha' => 'Milho 2026',
            'cultura' => 'Milho',
            'data' => '2026-10-18',
            'quantidade_total' => 12500,
            'referencia_externa' => 'colheita-2026-10-18-milho',
            'lotes' => [
                [
                    'codigo' => 'LOTE-2026-A',
                    'terreno' => $dados['terreno_norte']->nome,
                    'data_colheita' => '2026-10-18',
                    'quantidade' => 7000,
                    'unidade' => 'kg',
                ],
                [
                    'codigo' => 'LOTE-2026-B',
                    'terreno' => $dados['terreno_sul']->nome,
                    'data_colheita' => '2026-10-18',
                    'quantidade' => 5500,
                    'unidade' => 'kg',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.colheita.referencia_externa', 'colheita-2026-10-18-milho')
            ->assertJsonCount(2, 'dados.colheita.lotes');

        $this->assertDatabaseHas('colheitas', [
            'campanha_id' => $dados['campanha']->id,
            'cultura_id' => $dados['cultura']->id,
            'parcela_id' => $dados['parcela']->id,
            'referencia_externa' => 'colheita-2026-10-18-milho',
        ]);

        $lote = Lote::query()->where('numero_lote', 'LOTE-2026-A')->firstOrFail();

        $this->assertSame($dados['terreno_norte']->id, $lote->terreno_id);
        $this->assertSame('2026-10-18', $lote->data_colheita->toDateString());
    }

    public function test_gera_codigo_automatico_e_herda_data_colheita(): void
    {
        $this->autenticarApi();
        $dados = $this->criarDadosBase();

        $response = $this->postJson('/api/v1/colheitas', [
            'campanha' => 'Milho 2026',
            'cultura' => 'Milho',
            'data' => '2026-10-18',
            'quantidade_total' => 5500,
            'lotes' => [
                [
                    'terreno' => $dados['terreno_sul']->nome,
                    'quantidade' => 5500,
                    'unidade' => 'kg',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.colheita.lotes.0.codigo', 'LOTE-2026-001')
            ->assertJsonPath('dados.colheita.lotes.0.data_colheita', '2026-10-18');

        $lote = Lote::query()->where('numero_lote', 'LOTE-2026-001')->firstOrFail();

        $this->assertSame('2026-10-18', $lote->data_colheita->toDateString());
        $this->assertSame('2026-10-18', $lote->data_entrada->toDateString());
    }

    public function test_terreno_inexistente_devolve_erro_claro_e_nao_grava(): void
    {
        $this->autenticarApi();
        $this->criarDadosBase();

        $response = $this->postJson('/api/v1/colheitas', [
            'campanha' => 'Milho 2026',
            'cultura' => 'Milho',
            'data' => '2026-10-18',
            'quantidade_total' => 5500,
            'lotes' => [
                [
                    'terreno' => 'Terreno Fantasma',
                    'quantidade' => 5500,
                    'unidade' => 'kg',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment(['Referencia de terreno nao encontrada: Terreno Fantasma.']);

        $this->assertDatabaseCount('colheitas', 0);
        $this->assertDatabaseCount('lotes', 0);
    }

    public function test_idempotencia_devolve_colheita_existente_sem_criar_nova(): void
    {
        $this->autenticarApi();
        $dados = $this->criarDadosBase();

        Colheita::query()->create([
            'campanha_id' => $dados['campanha']->id,
            'cultura_id' => $dados['cultura']->id,
            'parcela_id' => $dados['parcela']->id,
            'data_colheita' => '2026-10-18',
            'quantidade_total' => 12500,
            'unidade_medida' => 'kg',
            'qualidade' => 'comercial',
            'referencia_externa' => 'colheita-2026-10-18-milho',
        ]);

        $response = $this->postJson('/api/v1/colheitas', [
            'campanha' => 'Milho 2026',
            'cultura' => 'Milho',
            'data' => '2026-10-19',
            'quantidade_total' => 9999,
            'referencia_externa' => 'colheita-2026-10-18-milho',
            'lotes' => [
                [
                    'terreno' => $dados['terreno_norte']->nome,
                    'quantidade' => 9999,
                    'unidade' => 'kg',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.colheita.referencia_externa', 'colheita-2026-10-18-milho')
            ->assertJsonFragment(['colheita ja registada (colheita-2026-10-18-milho)']);

        $this->assertDatabaseCount('colheitas', 1);
        $this->assertDatabaseCount('lotes', 0);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['colheitas:write']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function criarDadosBase(): array
    {
        $terrenoNorte = Terreno::query()->create([
            'nome' => 'Terreno Norte',
            'area_total' => 10,
        ]);

        $terrenoSul = Terreno::query()->create([
            'nome' => 'Terreno Sul',
            'area_total' => 8,
        ]);

        $parcela = Parcela::query()->create([
            'terreno_id' => $terrenoNorte->id,
            'nome' => 'Parcela Norte',
            'area_total' => 10,
        ]);

        $cultura = Cultura::query()->create([
            'parcela_id' => $parcela->id,
            'nome' => 'Milho',
            'tipo' => 'cereal',
            'data_plantacao' => '2026-03-01',
        ]);

        $campanha = Campanha::query()->create([
            'cultura_id' => $cultura->id,
            'ano' => 2026,
            'data_inicio' => '2026-03-01',
        ]);

        return [
            'terreno_norte' => $terrenoNorte,
            'terreno_sul' => $terrenoSul,
            'parcela' => $parcela,
            'cultura' => $cultura,
            'campanha' => $campanha,
        ];
    }
}
