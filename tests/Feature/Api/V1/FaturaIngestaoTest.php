<?php

namespace Tests\Feature\Api\V1;

use App\Models\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaturaIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_regista_fatura_com_despesa_linhas_stock_e_custo(): void
    {
        $this->autenticarApi();
        $montana = Produto::query()->create([
            'nome' => 'Montana',
            'tipo' => 'fitofarmaceutico',
            'numero_autorizacao_dgav' => 'DGAV-77',
            'unidade_medida' => 'L',
            'custo_unitario' => 40,
        ]);

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/123',
            'fornecedor' => 'Agro Silva Lda',
            'data' => '2026-08-20',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [
                [
                    'produto' => 'DGAV-77',
                    'descricao' => 'Montana 5L',
                    'quantidade' => 4,
                    'preco_unitario' => 45,
                    'iva_percentagem' => 6,
                ],
            ],
        ]);

        // 4 x 45 = 180 + 6% IVA = 190.80
        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.despesa.numero_fatura', 'FT 2026/123')
            ->assertJsonPath('dados.despesa.valor', '190.80')
            ->assertJsonPath('dados.despesa.linhas.0.produto.id', $montana->id)
            ->assertJsonPath('dados.custo.tipo', 'material')
            ->assertJsonPath('dados.custo.valor', '190.80')
            ->assertJsonCount(1, 'dados.movimentos_stock');

        $this->assertDatabaseHas('despesas', ['numero_fatura' => 'FT 2026/123', 'valor' => 190.80]);
        $this->assertDatabaseHas('fatura_items', ['descricao' => 'Montana 5L', 'quantidade' => 4]);
        $this->assertDatabaseHas('stocks', ['produto_id' => $montana->id, 'quantidade' => 4]);
        $this->assertDatabaseHas('movimento_stocks', [
            'produto_id' => $montana->id,
            'tipo' => 'entrada',
            'quantidade' => 4,
            'custo_unitario' => 45,
        ]);
        // custo_unitario do produto actualizado pelo preco da fatura
        $this->assertDatabaseHas('produtos', ['id' => $montana->id, 'custo_unitario' => 45]);
    }

    public function test_cria_produto_novo_quando_nao_existe(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/124',
            'fornecedor' => 'Agro Silva Lda',
            'data' => '2026-08-20',
            'linhas' => [
                [
                    'produto' => 'Adubo Foliar X',
                    'descricao' => 'Adubo Foliar X 20kg',
                    'quantidade' => 2,
                    'preco_unitario' => 30,
                    'tipo_produto' => 'fertilizante',
                    'unidade_medida' => 'kg',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['produto criado: Adubo Foliar X (tipo fertilizante).']);

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Adubo Foliar X',
            'tipo' => 'fertilizante',
            'unidade_medida' => 'kg',
            'custo_unitario' => 30,
        ]);
    }

    public function test_produto_fitofarmaceutico_novo_sem_dgav_devolve_422(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/125',
            'data' => '2026-08-20',
            'linhas' => [
                [
                    'produto' => 'Fito Novo',
                    'descricao' => 'Fito Novo 5L',
                    'quantidade' => 1,
                    'preco_unitario' => 50,
                    'tipo_produto' => 'fitofarmaceutico',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false);

        $this->assertDatabaseCount('despesas', 0);
        $this->assertDatabaseCount('produtos', 0);
    }

    public function test_total_divergente_gera_aviso_e_guarda_o_indicado(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/126',
            'data' => '2026-08-20',
            'valor' => 200,
            'linhas' => [
                [
                    'produto' => 'Adubo Y',
                    'descricao' => 'Adubo Y',
                    'quantidade' => 2,
                    'preco_unitario' => 30,
                    'tipo_produto' => 'fertilizante',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.despesa.valor', '200.00')
            ->assertJsonFragment([
                'o total indicado (200.00) nao bate com a soma das linhas com IVA (60.00); foi guardado o total indicado.',
            ]);
    }

    public function test_idempotencia_pelo_numero_de_fatura(): void
    {
        $this->autenticarApi();

        $payload = [
            'numero_fatura' => 'FT 2026/127',
            'fornecedor' => 'Agro Silva Lda',
            'data' => '2026-08-20',
            'linhas' => [
                [
                    'produto' => 'Adubo Z',
                    'descricao' => 'Adubo Z',
                    'quantidade' => 1,
                    'preco_unitario' => 10,
                    'tipo_produto' => 'fertilizante',
                ],
            ],
        ];

        $this->postJson('/api/v1/faturas', $payload)->assertCreated();
        $this->postJson('/api/v1/faturas', $payload)
            ->assertCreated()
            ->assertJsonFragment(['fatura ja registada (FT 2026/127)']);

        $this->assertDatabaseCount('despesas', 1);
        $this->assertDatabaseCount('custos', 1);
        $this->assertDatabaseHas('stocks', ['quantidade' => 1]);
    }

    public function test_pode_desligar_stock_e_custo(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/128',
            'data' => '2026-08-20',
            'dar_entrada_em_stock' => false,
            'criar_custo' => false,
            'linhas' => [
                [
                    'produto' => 'Adubo W',
                    'descricao' => 'Adubo W',
                    'quantidade' => 1,
                    'preco_unitario' => 10,
                    'tipo_produto' => 'fertilizante',
                ],
            ],
        ])->assertCreated()->assertJsonPath('dados.custo', null);

        $this->assertDatabaseCount('custos', 0);
        $this->assertDatabaseCount('movimento_stocks', 0);
    }

    public function test_iva_invalido_devolve_422(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas', [
            'data' => '2026-08-20',
            'linhas' => [
                [
                    'descricao' => 'Qualquer coisa',
                    'quantidade' => 1,
                    'preco_unitario' => 10,
                    'iva_percentagem' => 17,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment(['Taxa de IVA invalida. Valores aceites: 0, 6, 13, 23.']);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['custos:write']);

        return $user;
    }
}
