<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Lote;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceitaIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_insere_uma_receita_simples(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/receitas', [
            'descricao' => 'Subsidio PAC',
            'tipo' => 'subsidio',
            'valor' => 3200,
            'data' => '2026-12-05',
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.criados.0', 1)
            ->assertJsonPath('dados.receitas.0.data', '2026-12-05');

        $this->assertDatabaseHas('receitas', [
            'descricao' => 'Subsidio PAC',
            'tipo' => 'subsidio',
            'valor' => 3200,
        ]);
    }

    public function test_insere_um_lote_de_receitas(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/receitas', [
            'receitas' => [
                [
                    'descricao' => 'Venda de milho',
                    'tipo' => 'venda_colheita',
                    'valor' => 8450,
                    'data' => '2026-10-20',
                ],
                [
                    'descricao' => 'Subsidio PAC',
                    'tipo' => 'subsidio',
                    'valor' => 3200,
                    'data' => '2026-12-05',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(2, 'dados.criados');

        $this->assertDatabaseCount('receitas', 2);
    }

    public function test_tipo_invalido_devolve_422_com_valores_aceites(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/receitas', [
            'descricao' => 'Entrada estranha',
            'tipo' => 'donativo',
            'valor' => 10,
            'data' => '2026-12-05',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment([
                'Tipo invalido. Valores aceites: venda_colheita, subsidio, servico, outro.',
            ]);
    }

    public function test_idempotencia_nao_duplica_referencia_externa(): void
    {
        $this->autenticarApi();

        Receita::query()->create([
            'descricao' => 'Venda de milho',
            'tipo' => 'venda_colheita',
            'valor' => 8450,
            'data' => '2026-10-20',
            'referencia_externa' => 'venda-2026-10-20-milho',
        ]);

        $response = $this->postJson('/api/v1/receitas', [
            'descricao' => 'Venda duplicada',
            'tipo' => 'venda_colheita',
            'valor' => 9999,
            'data' => '2026-10-21',
            'referencia_externa' => 'venda-2026-10-20-milho',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.criados', [])
            ->assertJsonPath('dados.ignorados.0.referencia_externa', 'venda-2026-10-20-milho')
            ->assertJsonFragment(['receita ja registada (venda-2026-10-20-milho)']);

        $this->assertDatabaseCount('receitas', 1);
    }

    public function test_ligacao_por_nome_a_campanha_e_resolvida(): void
    {
        $this->autenticarApi();
        $dados = $this->criarDadosBase();

        $response = $this->postJson('/api/v1/receitas', [
            'descricao' => 'Venda de milho',
            'tipo' => 'venda_colheita',
            'valor' => 8450,
            'data' => '2026-10-20',
            'campanha' => 'Milho 2026',
            'comprador_nome' => 'Cooperativa Agricola',
            'documento' => 'FT 2026/338',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.receitas.0.campanha.id', $dados['campanha']->id);

        $this->assertDatabaseHas('receitas', [
            'campanha_id' => $dados['campanha']->id,
            'comprador_nome' => 'Cooperativa Agricola',
        ]);
    }

    public function test_liga_receita_a_colheita_e_lote(): void
    {
        $this->autenticarApi();
        $dados = $this->criarDadosBase();
        $colheita = $this->criarColheitaComLote($dados);
        $lote = $colheita->lotes()->firstOrFail();

        $response = $this->postJson('/api/v1/receitas', [
            'descricao' => 'Venda de milho - lote colheita',
            'tipo' => 'venda_colheita',
            'valor' => 8450,
            'data' => '2026-10-20',
            'colheita' => 'colheita-2026-10-18-milho',
            'lote' => 'LOTE-2026-A',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.receitas.0.colheita.id', $colheita->id)
            ->assertJsonPath('dados.receitas.0.lote.id', $lote->id);

        $this->assertDatabaseHas('receitas', [
            'colheita_id' => $colheita->id,
            'lote_id' => $lote->id,
        ]);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['receitas:write']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function criarDadosBase(): array
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

        $campanha = Campanha::query()->create([
            'cultura_id' => $cultura->id,
            'ano' => 2026,
            'data_inicio' => '2026-03-01',
        ]);

        return [
            'terreno' => $terreno,
            'parcela' => $parcela,
            'cultura' => $cultura,
            'campanha' => $campanha,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criarColheitaComLote(array $dados): Colheita
    {
        $colheita = Colheita::query()->create([
            'campanha_id' => $dados['campanha']->id,
            'cultura_id' => $dados['cultura']->id,
            'parcela_id' => $dados['parcela']->id,
            'data_colheita' => '2026-10-18',
            'quantidade_total' => 12500,
            'unidade_medida' => 'kg',
            'qualidade' => 'comercial',
            'referencia_externa' => 'colheita-2026-10-18-milho',
        ]);

        Lote::query()->create([
            'colheita_id' => $colheita->id,
            'terreno_id' => $dados['terreno']->id,
            'numero_lote' => 'LOTE-2026-A',
            'quantidade' => 7000,
            'unidade_medida' => 'kg',
            'data_colheita' => '2026-10-18',
            'data_entrada' => '2026-10-18',
        ]);

        return $colheita;
    }
}
