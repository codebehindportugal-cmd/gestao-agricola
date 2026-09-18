<?php

namespace Tests\Feature\Api\V1;

use App\Models\Alfaia;
use App\Models\Maquina;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pecas e reparacoes das alfaias.
 *
 * Um radiador comprado para o triturador era registado no tractor que o puxa,
 * porque a fatura so aceitava `maquina`. O gasto da alfaia ficava escondido
 * dentro do custo do tractor.
 */
class FaturaAlfaiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fatura_de_pecas_liga_a_despesa_e_o_custo_a_alfaia(): void
    {
        $this->autenticarApi();

        $verde = Maquina::query()->create(['nome' => 'Verde', 'tipo' => 'trator', 'marca' => 'Hurlimann']);
        $triturador = Alfaia::query()->create([
            'nome' => 'Trituradora Nova',
            'tipo' => 'triturador de restos',
            'maquina_id' => $verde->id,
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FR 4002A26',
            'fornecedor' => 'António Filipe Neto',
            'data' => '2026-07-08',
            'categoria' => 'pecas',
            'alfaia' => 'Trituradora Nova',
            'linhas' => [
                [
                    'descricao' => 'Termostato p/ refrigerador',
                    'quantidade' => 1,
                    'preco_unitario' => 42.26,
                    'desconto_percentagem' => 22.4,
                    'iva_percentagem' => 23,
                    'tipo_produto' => 'outro',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('dados.despesa.alfaia.id', $triturador->id)
            ->assertJsonPath('dados.custo.alfaia_id', $triturador->id)
            // A peca e gasto daquela alfaia, inteiro: nao se reparte pelas
            // campanhas do periodo.
            ->assertJsonPath('dados.custo.rateavel', false);

        $this->assertDatabaseHas('despesas', [
            'numero_fatura' => 'FR 4002A26',
            'alfaia_id' => $triturador->id,
            'maquina_id' => null,
        ]);

        $this->assertDatabaseHas('custos', [
            'tipo' => 'manutencao',
            'alfaia_id' => $triturador->id,
            'rateavel' => false,
        ]);
    }

    public function test_maquina_e_alfaia_juntas_gravam_as_duas(): void
    {
        $this->autenticarApi();

        $verde = Maquina::query()->create(['nome' => 'Verde', 'tipo' => 'trator']);
        $alfaia = Alfaia::query()->create(['nome' => 'Trituradora Nova', 'tipo' => 'triturador de restos']);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FR 100',
            'fornecedor' => 'Oficina do Zé',
            'data' => '2026-07-08',
            'categoria' => 'pecas',
            'maquina' => 'Verde',
            'alfaia' => 'Trituradora Nova',
            'linhas' => [
                ['descricao' => 'Revisão do conjunto', 'quantidade' => 1, 'preco_unitario' => 100, 'iva_percentagem' => 23],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('custos', [
            'maquina_id' => $verde->id,
            'alfaia_id' => $alfaia->id,
        ]);
    }

    public function test_alfaia_desconhecida_devolve_422_com_candidatos_parecidos(): void
    {
        $this->autenticarApi();

        $alfaia = Alfaia::query()->create(['nome' => 'Trituradora Nova', 'tipo' => 'triturador de restos']);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FR 101',
            'fornecedor' => 'Oficina do Zé',
            'data' => '2026-07-08',
            'categoria' => 'pecas',
            'alfaia' => 'Trituradora',
            'linhas' => [
                ['descricao' => 'Peça qualquer', 'quantidade' => 1, 'preco_unitario' => 10, 'iva_percentagem' => 23],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath('erros.alfaia.1.candidatos.0.id', $alfaia->id)
            ->assertJsonPath('erros.alfaia.1.candidatos.0.nome', 'Trituradora Nova');

        $this->assertDatabaseMissing('despesas', ['numero_fatura' => 'FR 101']);
    }

    public function test_id_de_alfaia_no_campo_maquina_resolve_para_outra_coisa(): void
    {
        $this->autenticarApi();

        // Guarda contra o erro de mandar o id da alfaia em `maquina`: nao da
        // erro nenhum, resolve para a maquina com esse id — que e outra coisa.
        // A fatura fica no tractor errado, em silencio.
        $maquina = Maquina::query()->create(['nome' => 'Verde', 'tipo' => 'trator']);
        $alfaia = Alfaia::query()->create(['nome' => 'Trituradora Nova', 'tipo' => 'triturador de restos']);

        $this->assertSame($maquina->id, $alfaia->id, 'o cenário só faz sentido com os ids iguais');

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FR 102',
            'fornecedor' => 'Oficina do Zé',
            'data' => '2026-07-08',
            'categoria' => 'pecas',
            'maquina' => $alfaia->id,
            'linhas' => [
                ['descricao' => 'Peça qualquer', 'quantidade' => 1, 'preco_unitario' => 10, 'iva_percentagem' => 23],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('despesas', [
            'numero_fatura' => 'FR 102',
            'maquina_id' => $maquina->id,
            'alfaia_id' => null,
        ]);
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
