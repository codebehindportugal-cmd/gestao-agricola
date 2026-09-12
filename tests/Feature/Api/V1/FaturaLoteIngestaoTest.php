<?php

namespace Tests\Feature\Api\V1;

use App\Http\Requests\Api\V1\StoreFaturasLoteApiRequest;
use App\Models\Despesa;
use App\Models\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/v1/faturas/lote — varias faturas num pedido.
 *
 * O que estes testes guardam e a garantia que faz o lote valer a pena: uma
 * fatura que falha nao arrasta as outras. Sem isso, quem fotografa dez faturas
 * de papel tem de repetir o lote inteiro por causa de uma.
 */
class FaturaLoteIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_regista_varias_faturas_num_pedido(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [
                $this->fatura('FT 2026/300', 'Adubo A', 2, 30),
                $this->fatura('FT 2026/301', 'Adubo B', 1, 50),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.total', 2)
            ->assertJsonPath('dados.registadas', 2)
            ->assertJsonPath('dados.repetidas', 0)
            ->assertJsonPath('dados.falhadas', 0)
            ->assertJsonPath('dados.faturas.0.estado', 'registada')
            ->assertJsonPath('dados.faturas.0.numero_fatura', 'FT 2026/300')
            ->assertJsonPath('dados.faturas.1.estado', 'registada')
            ->assertJsonCount(1, 'dados.faturas.0.dados.movimentos_stock');

        // O despesa_id de cada uma e' o que serve para enviar a foto a seguir.
        $this->assertNotNull($response->json('dados.faturas.0.despesa_id'));
        $this->assertNotNull($response->json('dados.faturas.1.despesa_id'));

        $this->assertDatabaseCount('despesas', 2);
        $this->assertDatabaseCount('custos', 2);
        $this->assertDatabaseHas('despesas', ['numero_fatura' => 'FT 2026/300']);
        $this->assertDatabaseHas('despesas', ['numero_fatura' => 'FT 2026/301']);
    }

    public function test_fatura_invalida_nao_impede_as_outras(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [
                $this->fatura('FT 2026/310', 'Adubo A', 2, 30),
                // Sem data e com IVA que nao existe: falha na validacao.
                [
                    'numero_fatura' => 'FT 2026/311',
                    'linhas' => [[
                        'produto' => 'Adubo B',
                        'descricao' => 'Adubo B 20 kg',
                        'quantidade' => 1,
                        'preco_unitario' => 10,
                        'iva_percentagem' => 17,
                    ]],
                ],
                $this->fatura('FT 2026/312', 'Adubo C', 3, 12),
            ],
        ]);

        // 207: ha faturas registadas para ver e uma para corrigir.
        $response->assertStatus(207)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('dados.registadas', 2)
            ->assertJsonPath('dados.falhadas', 1)
            ->assertJsonPath('dados.faturas.0.estado', 'registada')
            ->assertJsonPath('dados.faturas.1.estado', 'erro')
            ->assertJsonPath('dados.faturas.1.numero_fatura', 'FT 2026/311')
            ->assertJsonPath('dados.faturas.2.estado', 'registada');

        $this->assertNotEmpty($response->json('erros.faturas.1'));

        $this->assertDatabaseCount('despesas', 2);
        $this->assertDatabaseMissing('despesas', ['numero_fatura' => 'FT 2026/311']);
    }

    public function test_fatura_repetida_no_lote_nao_duplica(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas', $this->fatura('FT 2026/320', 'Adubo A', 2, 30))
            ->assertCreated();

        $response = $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [
                // A mesma de antes, e outra vez dentro do proprio lote.
                $this->fatura('FT 2026/320', 'Adubo A', 2, 30),
                $this->fatura('FT 2026/321', 'Adubo B', 1, 40),
                $this->fatura('FT 2026/321', 'Adubo B', 1, 40),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.registadas', 1)
            ->assertJsonPath('dados.repetidas', 2)
            ->assertJsonPath('dados.faturas.0.estado', 'repetida')
            ->assertJsonPath('dados.faturas.1.estado', 'registada')
            ->assertJsonPath('dados.faturas.2.estado', 'repetida')
            ->assertJsonFragment(['fatura ja registada (FT 2026/320)']);

        $this->assertSame(1, Despesa::query()->where('numero_fatura', 'FT 2026/320')->count());
        $this->assertSame(1, Despesa::query()->where('numero_fatura', 'FT 2026/321')->count());
        $this->assertDatabaseCount('despesas', 2);
    }

    public function test_lote_todo_invalido_devolve_422(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [
                ['numero_fatura' => 'FT 2026/330'],
                ['numero_fatura' => 'FT 2026/331'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('dados.falhadas', 2);

        $this->assertDatabaseCount('despesas', 0);
    }

    public function test_lote_vazio_e_acima_do_maximo_sao_rejeitados(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/faturas/lote', ['faturas' => []])
            ->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonStructure(['erros' => ['faturas']]);

        $muitas = array_map(
            fn (int $n) => $this->fatura("FT 2026/4{$n}", 'Adubo A', 1, 10),
            range(1, StoreFaturasLoteApiRequest::MAXIMO + 1)
        );

        $this->postJson('/api/v1/faturas/lote', ['faturas' => $muitas])
            ->assertStatus(422)
            ->assertJsonStructure(['erros' => ['faturas']]);

        $this->assertDatabaseCount('despesas', 0);
    }

    public function test_lote_exige_token_com_ability_de_escrita(): void
    {
        $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [$this->fatura('FT 2026/340', 'Adubo A', 1, 10)],
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->create(['name' => 'operador']));
        Sanctum::actingAs($user, ['colheitas:write']);

        $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [$this->fatura('FT 2026/341', 'Adubo A', 1, 10)],
        ])->assertForbidden();

        $this->assertDatabaseCount('despesas', 0);
    }

    public function test_cada_fatura_do_lote_da_entrada_em_stock_pelo_tamanho_da_embalagem(): void
    {
        $this->autenticarApi();

        $banjo = Produto::query()->create([
            'nome' => 'BANJO fluziname',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 1199',
            'unidade_medida' => 'L',
            'conteudo' => 5,
            'custo_unitario' => 30,
        ]);

        $this->postJson('/api/v1/faturas/lote', [
            'faturas' => [
                [
                    'numero_fatura' => 'FT 2026/350',
                    'fornecedor' => 'Casa Queridos',
                    'data' => '2026-08-20',
                    'categoria' => 'fitofarmaceuticos',
                    'linhas' => [[
                        'produto' => 'BANJO fluziname',
                        'codigo' => 'BJ-5',
                        'descricao' => 'BANJO fluziname - 5 LT',
                        'quantidade' => 2,
                        'conteudo_embalagem' => 5,
                        'unidade_embalagem' => 'L',
                        'preco_unitario' => 164.90,
                        'iva_percentagem' => 6,
                    ]],
                ],
            ],
        ])->assertCreated();

        // 2 bidoes de 5 L sao 10 L em stock, a 32,98 EUR/L — nao 2 unidades.
        $this->assertDatabaseHas('stocks', ['produto_id' => $banjo->id, 'quantidade' => 10]);
        $this->assertDatabaseHas('movimento_stocks', [
            'produto_id' => $banjo->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'custo_unitario' => 32.98,
        ]);
    }

    /** @return array<string, mixed> */
    private function fatura(string $numero, string $produto, float $quantidade, float $preco): array
    {
        return [
            'numero_fatura' => $numero,
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-08-20',
            'categoria' => 'fertilizantes',
            'linhas' => [[
                'produto' => $produto,
                'descricao' => $produto.' 20 kg',
                'quantidade' => $quantidade,
                'conteudo_embalagem' => 20,
                'unidade_embalagem' => 'kg',
                'preco_unitario' => $preco,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fertilizante',
            ]],
        ];
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
