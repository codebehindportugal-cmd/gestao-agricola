<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Despesa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Uma fatura que serve varias campanhas nao pertence a nenhuma.
 *
 * Nesta exploracao correm tres campanhas ao mesmo tempo (pereiras, macieiras,
 * culturas anuais) com o mesmo periodo, por isso nao existe "a campanha
 * activa". Quando a API nao consegue escolher uma, a despesa fica sem campanha
 * e o custo nasce rateavel. O que estes testes fixam e que esse dinheiro
 * continua a ser visto: ficou escondido do ecra de despesas uma vez e o gasto
 * desapareceu sem ninguem dar por ela.
 */
class DespesaSemCampanhaTest extends TestCase
{
    use RefreshDatabase;

    public function test_despesa_sem_campanha_aparece_no_ecra_das_despesas(): void
    {
        $this->autenticar();
        $campanha = $this->campanha('Pereiras 2026');

        $daCampanha = Despesa::query()->create([
            'titulo' => 'Adubo das pereiras',
            'valor' => 100,
            'data' => '2026-04-10',
            'categoria' => 'fertilizantes',
            'campanha_id' => $campanha->id,
        ]);

        $geral = Despesa::query()->create([
            'titulo' => 'Fungicida para o pomar todo',
            'valor' => 350,
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'campanha_id' => null,
        ]);

        session(['campanha_ativa_id' => $campanha->id, 'campanha_ativa_ano' => 2026]);

        $this->get(route('app.despesas.index', ['mes' => 4, 'ano' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('despesas.data', 2)
                ->where('resumoMes.total', 450));

        // E a mesma conta vista do outro lado: se o filtro escondesse a despesa
        // sem campanha, o mes ficava com 100 euros em vez de 450.
        $this->assertNotNull($daCampanha->campanha_id);
        $this->assertNull($geral->campanha_id);
    }

    public function test_resumo_do_mes_conta_a_despesa_sem_campanha(): void
    {
        $this->autenticar();
        $this->campanha('Pereiras 2026');

        Despesa::query()->create([
            'titulo' => 'Fungicida para o pomar todo',
            'valor' => 350,
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'campanha_id' => null,
        ]);

        session(['campanha_ativa_ano' => 2026]);

        $this->get(route('app.despesas.index', ['mes' => 4, 'ano' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resumoMes.total', 350)
                ->where('resumoMes.count', 1));
    }

    public function test_api_escolhe_a_campanha_quando_so_uma_cobre_a_data(): void
    {
        $this->autenticarApi();
        $unica = $this->campanha('Pereiras 2026');

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/500',
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-04-10',
            'categoria' => 'fertilizantes',
            'linhas' => [[
                'produto' => 'STOOP CA-B',
                'descricao' => 'STOOP CA-B 25 kg',
                'quantidade' => 2,
                'preco_unitario' => 100,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fertilizante',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.despesa.campanha.id', $unica->id)
            ->assertJsonPath('dados.custo.rateavel', false);

        $this->assertDatabaseHas('despesas', [
            'numero_fatura' => 'FT 2026/500',
            'campanha_id' => $unica->id,
        ]);
    }

    public function test_api_deixa_sem_campanha_e_marca_rateavel_quando_ha_varias(): void
    {
        $this->autenticarApi();
        $this->campanha('Pereiras 2026');
        $this->campanha('Macieiras 2026');
        $this->campanha('Culturas anuais 2026');

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/501',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'produto' => 'BANJO fluziname',
                'descricao' => 'BANJO fluziname - 5 LT',
                'numero_autorizacao_dgav' => 'AV 1199',
                'quantidade' => 2,
                'preco_unitario' => 164.90,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fitofarmaco',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('dados.despesa.campanha', null)
            ->assertJsonPath('dados.custo.rateavel', true)
            ->assertJsonPath('dados.custo.base_rateio', 'kg');

        $avisos = $response->json('avisos');
        $this->assertTrue(
            collect($avisos)->contains(fn ($aviso) => str_contains($aviso, '3 campanhas')),
            'o aviso tem de dizer quais as campanhas em causa: '.json_encode($avisos)
        );
    }

    public function test_campanha_indicada_no_pedido_ganha_a_data(): void
    {
        $this->autenticarApi();
        $this->campanha('Pereiras 2026');
        $macieiras = $this->campanha('Macieiras 2026');

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/502',
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-04-10',
            'categoria' => 'fertilizantes',
            'campanha' => 'Macieiras 2026',
            'linhas' => [[
                'produto' => 'SILECKO',
                'descricao' => 'SILECKO 25 kg',
                'quantidade' => 1,
                'preco_unitario' => 80,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fertilizante',
            ]],
        ])->assertCreated()
            ->assertJsonPath('dados.despesa.campanha.id', $macieiras->id)
            ->assertJsonPath('dados.custo.rateavel', false);
    }

    public function test_fatura_de_pecas_com_maquina_nao_fica_rateavel(): void
    {
        $this->autenticarApi();
        $this->campanha('Pereiras 2026');
        $this->campanha('Macieiras 2026');

        $landini = \App\Models\Maquina::query()->create([
            'nome' => 'Landini',
            'tipo' => 'trator',
            'marca' => 'Landini',
            'modelo' => 'Rex 100',
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 2026/503',
            'fornecedor' => 'Oficina do Ze',
            'data' => '2026-04-10',
            'categoria' => 'pecas',
            'maquina' => 'Landini',
            'linhas' => [[
                'descricao' => 'Chumaceira',
                'quantidade' => 1,
                'preco_unitario' => 30,
                'iva_percentagem' => 23,
            ]],
        ])->assertCreated()
            ->assertJsonPath('dados.custo.rateavel', false);

        $this->assertDatabaseHas('custos', [
            'maquina_id' => $landini->id,
            'rateavel' => false,
        ]);
    }

    public function test_despesas_nao_tem_marca(): void
    {
        $this->assertNotContains('marca', (new Despesa())->getFillable());
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('despesas', 'marca'),
            'a coluna marca veio da Horta da Maria e nao tem significado nesta exploracao'
        );
    }

    private function campanha(string $nome): Campanha
    {
        return Campanha::query()->create([
            'nome' => $nome,
            'ano' => 2026,
            'data_inicio' => '2025-10-01',
            'data_fim' => '2026-09-30',
            'status' => 'em_curso',
        ]);
    }

    private function autenticar(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($role);

        $this->actingAs($user);

        return $user;
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['faturas:write', 'custos:write']);

        return $user;
    }
}
