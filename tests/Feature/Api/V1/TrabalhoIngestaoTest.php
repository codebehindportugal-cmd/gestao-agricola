<?php

namespace Tests\Feature\Api\V1;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Equipa;
use App\Models\Funcionario;
use App\Models\Parcela;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrabalhoIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_operacao_jornadas_e_custo_com_funcionarios(): void
    {
        $this->autenticarApi();
        $contexto = $this->criarContexto();
        $ana = $this->criarFuncionario('Ana Silva', 6);
        $bruno = $this->criarFuncionario('Bruno Costa', 5);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data_inicio' => '2026-08-14',
            'dias' => 3,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'funcionarios' => [$ana->nome, $bruno->id],
            'referencia_externa' => 'apanha-2026-08-14',
        ]);

        // 2 pessoas x 3 dias x 8h = 48h; custo = (6 + 5) x 8 x 3 = 264
        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.dias_trabalhados', 3)
            ->assertJsonPath('dados.jornadas', 6)
            ->assertJsonPath('dados.custo.valor', '264.00')
            ->assertJsonPath('dados.operacao.tipo', 'colheita');

        $this->assertDatabaseCount('jornadas', 6);
        $this->assertDatabaseHas('jornadas', [
            'funcionario_id' => $ana->id,
            'data' => '2026-08-14',
            'horas_trabalhadas' => 8,
            'tarefa' => 'Apanha da fruta',
        ]);
        $this->assertDatabaseHas('custos', [
            'tipo' => 'mao_obra',
            'valor' => 264,
            'campanha_id' => $contexto['campanha']->id,
            'parcela_id' => $contexto['parcela']->id,
        ]);
        $this->assertDatabaseHas('operacoes', [
            'referencia_externa' => 'apanha-2026-08-14',
            'duracao_horas' => 48,
            'custo_real' => 264,
        ]);
    }

    public function test_fins_de_semana_sao_excluidos_por_defeito(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $ana = $this->criarFuncionario('Ana Silva', 10);

        // 2026-08-14 e sexta; 15 sabado, 16 domingo, 17 segunda.
        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Poda',
            'data_inicio' => '2026-08-14',
            'data_fim' => '2026-08-17',
            'horas_por_dia' => 8,
            'funcionarios' => [$ana->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.dias_trabalhados', 2)
            ->assertJsonPath('dados.jornadas', 2);

        $this->assertDatabaseHas('jornadas', ['data' => '2026-08-14']);
        $this->assertDatabaseHas('jornadas', ['data' => '2026-08-17']);
        $this->assertDatabaseMissing('jornadas', ['data' => '2026-08-15']);
    }

    public function test_equipa_traz_os_funcionarios_associados(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $ana = $this->criarFuncionario('Ana Silva', 6);
        $bruno = $this->criarFuncionario('Bruno Costa', 6);
        $equipa = Equipa::query()->create(['nome' => 'Equipa Pomar']);
        $equipa->funcionarios()->attach([$ana->id, $bruno->id]);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'data_inicio' => '2026-08-14',
            'dias' => 2,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'equipa' => 'Equipa Pomar',
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.jornadas', 4)
            ->assertJsonPath('dados.custo.valor', '192.00');

        $this->assertDatabaseHas('operacoes', ['equipa_id' => $equipa->id]);
    }

    public function test_numero_pessoas_sem_funcionarios_cria_apenas_custo_agregado(): void
    {
        $this->autenticarApi();
        $this->criarContexto();

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'campanha' => 'Milho 2026',
            'data_inicio' => '2026-08-14',
            'semanas' => 3,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'numero_pessoas' => 18,
            'valor_hora' => 5,
        ]);

        // 18 pessoas x 21 dias x 8h x 5 EUR = 15120
        $response->assertCreated()
            ->assertJsonPath('dados.dias_trabalhados', 21)
            ->assertJsonPath('dados.jornadas', 0)
            ->assertJsonPath('dados.custo.valor', '15120.00');

        $this->assertDatabaseCount('jornadas', 0);
        $this->assertDatabaseHas('operacoes', ['duracao_horas' => 3024]);
    }

    public function test_custo_total_explicito_sobrepoe_o_calculo(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $ana = $this->criarFuncionario('Ana Silva', 6);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'data_inicio' => '2026-08-14',
            'dias' => 1,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'funcionarios' => [$ana->id],
            'custo_total' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.custo.valor', '500.00');
    }

    public function test_periodo_em_falta_devolve_422(): void
    {
        $this->autenticarApi();
        $this->criarContexto();

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'data_inicio' => '2026-08-14',
            'horas_por_dia' => 8,
            'numero_pessoas' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('sucesso', false)
            ->assertJsonPath(
                'erros.data_fim.0',
                'Indique data_fim, dias ou semanas para delimitar o periodo de trabalho.'
            );
    }

    public function test_sem_pessoas_devolve_422(): void
    {
        $this->autenticarApi();
        $this->criarContexto();

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'data_inicio' => '2026-08-14',
            'dias' => 2,
            'horas_por_dia' => 8,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('erros.funcionarios.0', 'Indique funcionarios, equipa ou numero_pessoas.');
    }

    public function test_idempotencia_nao_duplica_o_trabalho(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $ana = $this->criarFuncionario('Ana Silva', 6);

        $payload = [
            'tarefa' => 'Apanha da fruta',
            'data_inicio' => '2026-08-14',
            'dias' => 2,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'funcionarios' => [$ana->id],
            'referencia_externa' => 'apanha-2026-08-14',
        ];

        $this->postJson('/api/v1/trabalhos', $payload)->assertCreated();
        $segunda = $this->postJson('/api/v1/trabalhos', $payload);

        $segunda->assertCreated()
            ->assertJsonFragment(['trabalho ja registado (apanha-2026-08-14)']);

        $this->assertDatabaseCount('operacoes', 1);
        $this->assertDatabaseCount('jornadas', 2);
    }

    public function test_funcionario_sem_valor_hora_gera_aviso(): void
    {
        $this->autenticarApi();
        $this->criarContexto();
        $sem = $this->criarFuncionario('Sem Valor', null);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Limpeza',
            'data_inicio' => '2026-08-14',
            'dias' => 1,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'funcionarios' => [$sem->id],
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['sem valor_hora definido para: Sem Valor; essas horas entraram no custo a zero.'])
            ->assertJsonPath('dados.custo', null);

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

    private function criarFuncionario(string $nome, float|int|null $valorHora): Funcionario
    {
        return Funcionario::query()->create([
            'nome' => $nome,
            'cargo' => 'Operador agricola',
            'data_admissao' => '2026-01-01',
            'valor_hora' => $valorHora,
            'status' => 'ativo',
        ]);
    }

    private function criarContexto(): array
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno Norte', 'area_total' => 10]);
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
}
