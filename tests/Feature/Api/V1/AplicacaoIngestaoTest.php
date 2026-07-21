<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Produto;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AplicacaoIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_aplicacao_completa_com_dois_produtos(): void
    {
        $this->autenticarApi();
        $contexto = $this->criarContexto();
        $herbicida = Produto::query()->create([
            'nome' => 'Herbicida Norte',
            'tipo' => 'fitofarmaceutico',
            'numero_autorizacao_dgav' => '3456',
            'unidade_medida' => 'L',
        ]);
        $fertilizante = Produto::query()->create([
            'nome' => 'Fertilizante Verde',
            'tipo' => 'fertilizante',
            'unidade_medida' => 'kg',
        ]);

        $response = $this->postJson('/api/v1/aplicacoes', [
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data' => '2026-07-16',
            'tipo' => 'tratamento',
            'produtor_nome' => 'Andre',
            'aplicador_nome' => 'Andre',
            'aplicador_numero_autorizacao' => 'PT-12345',
            'exploracao_concelho' => 'Santarem',
            'exploracao_freguesia' => 'Alcanhoes',
            'referencia_externa' => 'aplic-2026-0116',
            'produtos' => [
                [
                    'produto' => $herbicida->id,
                    'quantidade' => 5,
                    'dose' => 2.5,
                    'dose_unidade' => 'L/ha',
                    'area_tratada' => 2,
                    'volume_calda' => 400,
                    'finalidade' => 'Controlo de infestantes',
                    'intervalo_seguranca_dias' => 30,
                    'custo_unitario' => 18.90,
                ],
                [
                    'produto' => $fertilizante->nome,
                    'quantidade' => 12,
                    'custo_unitario' => 3.25,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.operacao.campanha.id', $contexto['campanha']->id)
            ->assertJsonPath('dados.operacao.parcela.id', $contexto['parcela']->id)
            ->assertJsonPath('dados.operacao.cultura.id', $contexto['cultura']->id)
            ->assertJsonCount(2, 'dados.operacao.produtos')
            ->assertJsonPath('dados.operacao.produtos.0.custo_total', 94.5)
            ->assertJsonPath('dados.operacao.produtos.1.custo_total', 39);

        $this->assertDatabaseHas('operacoes', [
            'tipo' => 'tratamento',
            'campanha_id' => $contexto['campanha']->id,
            'parcela_id' => $contexto['parcela']->id,
            'referencia_externa' => 'aplic-2026-0116',
        ]);
        $this->assertDatabaseCount('operacao_produtos', 2);
    }

    public function test_produto_e_resolvido_por_numero_autorizacao_dgav(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        Produto::query()->create([
            'nome' => 'Produto DGAV',
            'tipo' => 'fitofarmaceutico',
            'numero_autorizacao_dgav' => 'DGAV-99',
            'unidade_medida' => 'L',
        ]);

        $response = $this->postJson('/api/v1/aplicacoes', $this->payloadBase([
            'produtos' => [
                [
                    'produto' => 'DGAV-99',
                    'quantidade' => 2,
                    'custo_unitario' => 10,
                ],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('dados.operacao.produtos.0.numero_autorizacao_dgav', 'DGAV-99')
            ->assertJsonPath('dados.operacao.produtos.0.custo_total', 20);
    }

    public function test_falta_de_produtos_devolve_422(): void
    {
        $this->autenticarApi();
        $this->criarContexto();

        $response = $this->postJson('/api/v1/aplicacoes', [
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data' => '2026-07-16',
            'tipo' => 'tratamento',
            'produtos' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('erros.produtos.0', 'The produtos field is required.');
    }

    public function test_produto_fitofarmaceutico_sem_numero_dgav_devolve_422(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        Produto::query()->create([
            'nome' => 'Produto Sem DGAV',
            'tipo' => 'fitofarmaceutico',
            'unidade_medida' => 'L',
        ]);

        $response = $this->postJson('/api/v1/aplicacoes', $this->payloadBase([
            'produtos' => [
                [
                    'produto' => 'Produto Sem DGAV',
                    'quantidade' => 2,
                ],
            ],
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonFragment([
                'Produto fitofarmaceutico sem numero_autorizacao_dgav; registo nao conforme DGAV.',
            ]);

        $this->assertDatabaseCount('operacoes', 0);
    }

    public function test_idempotencia_devolve_operacao_existente_sem_criar_nova(): void
    {
        $this->autenticarApi();
        $contexto = $this->criarContexto();
        $produto = Produto::query()->create([
            'nome' => 'Produto DGAV',
            'tipo' => 'fitofarmaceutico',
            'numero_autorizacao_dgav' => 'DGAV-99',
            'unidade_medida' => 'L',
        ]);
        $operacao = Operacao::query()->create([
            'campanha_id' => $contexto['campanha']->id,
            'parcela_id' => $contexto['parcela']->id,
            'cultura_id' => $contexto['cultura']->id,
            'tipo' => 'tratamento',
            'data_hora_inicio' => '2026-07-16 00:00:00',
            'referencia_externa' => 'aplic-2026-0116',
            'estado' => 'concluida',
        ]);
        $operacao->produtos()->attach($produto->id, [
            'quantidade' => 1,
            'unidade_medida' => 'L',
        ]);

        $response = $this->postJson('/api/v1/aplicacoes', $this->payloadBase([
            'referencia_externa' => 'aplic-2026-0116',
            'produtos' => [
                [
                    'produto' => 'DGAV-99',
                    'quantidade' => 99,
                ],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('dados.operacao.id', $operacao->id)
            ->assertJsonFragment(['aplicacao ja registada (aplic-2026-0116)']);

        $this->assertDatabaseCount('operacoes', 1);
        $this->assertDatabaseCount('operacao_produtos', 1);
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

        return compact('terreno', 'parcela', 'cultura', 'campanha');
    }

    private function payloadBase(array $override = []): array
    {
        return array_replace_recursive([
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data' => '2026-07-16',
            'tipo' => 'tratamento',
            'produtos' => [
                [
                    'produto' => 'DGAV-99',
                    'quantidade' => 2,
                ],
            ],
        ], $override);
    }
}
