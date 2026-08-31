<?php

namespace Tests\Feature\Api\V1;

use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Maquina;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AplicacaoMultiParcelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_uma_operacao_por_parcela_com_meios_e_horas_distribuidas(): void
    {
        $this->autenticarApi();
        $contexto = $this->criarContexto();
        $produto = $this->criarProduto();

        $maquina = Maquina::query()->create([
            'nome' => 'Landini',
            'tipo' => 'trator',
            'marca' => 'Landini',
            'modelo' => 'Rex 100',
        ]);
        $alfaia = Alfaia::query()->create([
            'nome' => 'Pulverizador 800L',
            'tipo' => 'pulverizador',
        ]);

        $response = $this->postJson('/api/v1/aplicacoes', [
            'campanha' => 'Milho 2026',
            'data' => '2026-08-20',
            'maquina' => 'Landini',
            'alfaia' => 'Pulverizador 800L',
            'duracao_horas' => 16,
            'combustivel_gasto_l' => 40,
            'referencia_externa' => 'pulv-2026-08-20',
            'produtos' => [
                [
                    'produto' => 'DGAV-77',
                    'dose' => 2,
                    'dose_unidade' => 'L/ha',
                    'custo_unitario' => 10,
                ],
            ],
            'parcelas' => [
                ['parcela' => 'Parcela Norte', 'area_tratada' => 2],
                ['parcela' => 'Parcela Sul', 'area_tratada' => 6],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'dados.operacoes')
            ->assertJsonPath('dados.operacoes.0.parcela.id', $contexto['parcela']->id)
            ->assertJsonPath('dados.operacoes.1.parcela.id', $contexto['parcelaSul']->id)
            ->assertJsonCount(1, 'dados.operacoes.0.produtos');

        // dose 2 L/ha x 2 ha = 4 L (40 EUR); x 6 ha = 12 L (120 EUR)
        $this->assertDatabaseHas('operacao_produtos', [
            'produto_id' => $produto->id,
            'quantidade' => 4,
            'custo_total' => 40,
        ]);
        $this->assertDatabaseHas('operacao_produtos', [
            'produto_id' => $produto->id,
            'quantidade' => 12,
            'custo_total' => 120,
        ]);

        // 16h distribuidas proporcionalmente a area: 2/8 e 6/8
        $this->assertDatabaseHas('operacoes', [
            'referencia_externa' => 'pulv-2026-08-20-1',
            'maquina_id' => $maquina->id,
            'alfaia_id' => $alfaia->id,
            'duracao_horas' => 4,
            'combustivel_gasto_l' => 10,
        ]);
        $this->assertDatabaseHas('operacoes', [
            'referencia_externa' => 'pulv-2026-08-20-2',
            'duracao_horas' => 12,
            'combustivel_gasto_l' => 30,
        ]);
        $this->assertDatabaseCount('operacoes', 2);
    }

    public function test_tipo_por_omissao_e_tratamento_fitossanitario(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $this->criarProduto();

        $this->postJson('/api/v1/aplicacoes', [
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data' => '2026-08-20',
            'produtos' => [['produto' => 'DGAV-77', 'quantidade' => 3]],
        ])->assertCreated();

        $this->assertDatabaseHas('operacoes', ['tipo' => 'tratamento fitossanitário']);
    }

    public function test_idempotencia_com_varias_parcelas_nao_duplica(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $this->criarProduto();

        $payload = [
            'campanha' => 'Milho 2026',
            'data' => '2026-08-20',
            'referencia_externa' => 'pulv-2026-08-20',
            'produtos' => [['produto' => 'DGAV-77', 'quantidade' => 3]],
            'parcelas' => [
                ['parcela' => 'Parcela Norte'],
                ['parcela' => 'Parcela Sul'],
            ],
        ];

        $this->postJson('/api/v1/aplicacoes', $payload)->assertCreated();
        $this->postJson('/api/v1/aplicacoes', $payload)
            ->assertCreated()
            ->assertJsonFragment(['aplicacao ja registada (pulv-2026-08-20)'])
            ->assertJsonCount(2, 'dados.operacoes');

        $this->assertDatabaseCount('operacoes', 2);
    }

    public function test_parcela_inexistente_nao_grava_nada(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $this->criarProduto();

        $response = $this->postJson('/api/v1/aplicacoes', [
            'campanha' => 'Milho 2026',
            'data' => '2026-08-20',
            'produtos' => [['produto' => 'DGAV-77', 'quantidade' => 3]],
            'parcelas' => [
                ['parcela' => 'Parcela Norte'],
                ['parcela' => 'Martinho'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false);

        $this->assertDatabaseCount('operacoes', 0);
    }

    private function criarProduto(): Produto
    {
        return Produto::query()->create([
            'nome' => 'Montana',
            'tipo' => 'fitofarmaceutico',
            'numero_autorizacao_dgav' => 'DGAV-77',
            'unidade_medida' => 'L',
        ]);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['aplicacoes:write']);

        return $user;
    }

    private function criarContexto(): array
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno Norte', 'area_total' => 20]);

        $parcela = Parcela::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Parcela Norte',
            'area_total' => 10,
        ]);
        $parcelaSul = Parcela::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Parcela Sul',
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

        return compact('terreno', 'parcela', 'parcelaSul', 'cultura', 'campanha');
    }
}
