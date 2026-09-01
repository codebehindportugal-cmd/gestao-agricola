<?php

namespace Tests\Feature\Api\V1;

use App\Models\Maquina;
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

    public function test_fatura_de_pecas_liga_o_custo_a_maquina(): void
    {
        $this->autenticarApi();
        $landini = Maquina::query()->create([
            'nome' => 'Landini',
            'tipo' => 'trator',
            'marca' => 'Landini',
            'modelo' => 'Rex 100',
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/900',
            'fornecedor' => 'Oficina do Zé',
            'data' => '2026-08-20',
            'categoria' => 'pecas',
            'maquina' => 'Landini',
            'linhas' => [
                [
                    'descricao' => 'Filtro de óleo',
                    'quantidade' => 2,
                    'preco_unitario' => 15,
                    'iva_percentagem' => 23,
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('custos', [
            'tipo' => 'manutencao',
            'maquina_id' => $landini->id,
            'valor' => 36.90,
        ]);
    }

    public function test_guarda_o_estabelecimento_de_venda_no_produto(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => '136375',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-08-07',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [
                [
                    'produto' => 'ERUNE',
                    'descricao' => 'ERUNE pirimetanil - 5 LT',
                    'quantidade' => 5,
                    'preco_unitario' => 20.70,
                    'iva_percentagem' => 6,
                    'tipo_produto' => 'fitofarmaceutico',
                    'numero_autorizacao_dgav' => '1761',
                    'unidade_medida' => 'L',
                    'estabelecimento_venda_autorizacao' => '889-V-R',
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('produtos', [
            'nome' => 'ERUNE',
            'numero_autorizacao_dgav' => '1761',
            'unidade_medida' => 'L',
            // o nome do estabelecimento cai para o fornecedor da fatura
            'estabelecimento_venda_nome' => 'Casa Queridos',
            'estabelecimento_venda_autorizacao' => '889-V-R',
        ]);
        $this->assertDatabaseHas('stocks', ['quantidade' => 5, 'unidade_medida' => 'L']);
    }

    public function test_produto_criado_fica_com_o_tipo_canonico_da_aplicacao(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/901',
            'data' => '2026-08-20',
            'linhas' => [[
                'produto' => 'Fito Com DGAV',
                'descricao' => 'Fito Com DGAV 5L',
                'quantidade' => 5,
                'preco_unitario' => 20,
                // grafia da API; a aplicacao usa 'fitofarmaco'
                'tipo_produto' => 'fitofarmaceutico',
                'numero_autorizacao_dgav' => '9999',
            ]],
        ])->assertCreated();

        // Guardado como 'fitofarmaco', senao o Stock e o formulario de
        // operacoes nao o encontram nos filtros.
        $this->assertDatabaseHas('produtos', [
            'nome' => 'Fito Com DGAV',
            'tipo' => 'fitofarmaco',
        ]);
    }

    public function test_fitofarmaco_existente_sem_dgav_e_recusado_na_aplicacao(): void
    {
        $this->autenticarApi();

        // Tipo tal como a UI o grava.
        $produto = Produto::query()->create([
            'nome' => 'Sem DGAV',
            'tipo' => 'fitofarmaco',
            'unidade_medida' => 'L',
        ]);

        $this->assertTrue($produto->ehFitofarmaco());
        $this->assertFalse(Produto::query()->create([
            'nome' => 'Adubo qualquer',
            'tipo' => 'fertilizante',
        ])->ehFitofarmaco());
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
