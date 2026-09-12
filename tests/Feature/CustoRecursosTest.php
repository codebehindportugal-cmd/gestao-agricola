<?php

namespace Tests\Feature;

use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use App\Services\CustoRecursosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Custo das maquinas e do transporte de uma operacao.
 *
 * Uma operacao so tinha lugar para uma maquina e uma alfaia, por isso uma
 * apanha com dois tratores, dois empilhadores de campo e um carro ficava
 * registada apenas com a mao de obra e o custo/kg da campanha saia por defeito.
 */
class CustoRecursosTest extends TestCase
{
    use RefreshDatabase;

    /** O caso real: 20 pessoas a 60 EUR/dia, dois tratores, dois empilhadores e um carro. */
    public function test_apanha_com_varias_maquinas_soma_tudo_ao_custo_da_operacao(): void
    {
        $this->autenticarApi();
        $contexto = $this->criarContexto();

        // Um trator com o custo/hora no cadastro: a linha do pedido nao o repete.
        $landini = Maquina::query()->create([
            'nome' => 'Trator Landini', 'tipo' => 'trator', 'custo_hora' => 18,
        ]);
        $johnDeere = Maquina::query()->create([
            'nome' => 'Trator John Deere', 'tipo' => 'trator',
        ]);
        $empilhador = Alfaia::query()->create([
            'nome' => 'Empilhador de campo', 'tipo' => 'empilhador', 'custo_hora' => 6,
        ]);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data_inicio' => '2026-08-03',
            'dias' => 15,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'numero_pessoas' => 20,
            'valor_hora' => 7.5, // 60 EUR/dia a 8h
            'maquinas' => [
                ['maquina' => $landini->nome],
                ['maquina' => $johnDeere->id, 'custo_hora' => 18],
                ['alfaia' => $empilhador->id, 'unidades' => 2],
                ['nome' => 'Carro de transporte', 'papel' => 'transporte', 'km' => 900, 'custo_km' => 0.40],
            ],
            'referencia_externa' => 'apanha-2026-08-03',
        ]);

        $response->assertCreated()->assertJsonPath('sucesso', true);

        // Mao de obra: 20 pessoas x 15 dias x 8h x 7,50 = 18 000 (= 20 x 60 x 15).
        $this->assertDatabaseHas('custos', ['tipo' => 'mao_obra', 'valor' => 18000]);

        // Cada trator: 8h x 15 dias = 120h a 18 EUR = 2 160.
        // Empilhadores: 120h a 6 EUR x 2 unidades = 1 440.
        // Carro: 900 km a 0,40 = 360.
        $this->assertDatabaseCount('operacao_recursos', 4);
        $this->assertDatabaseHas('operacao_recursos', ['maquina_id' => $landini->id, 'horas' => 120, 'custo_total' => 2160]);
        $this->assertDatabaseHas('operacao_recursos', ['maquina_id' => $johnDeere->id, 'custo_total' => 2160]);
        $this->assertDatabaseHas('operacao_recursos', ['alfaia_id' => $empilhador->id, 'unidades' => 2, 'custo_total' => 1440]);
        $this->assertDatabaseHas('operacao_recursos', ['nome' => 'Carro de transporte', 'km' => 900, 'custo_total' => 360]);

        // Um Custo por recurso, para se poder perguntar o que custou cada maquina.
        $this->assertSame(4, Custo::query()->where('tipo', 'maquina')->count());

        // 18 000 + 2 160 + 2 160 + 1 440 + 360 = 24 120.
        $this->assertDatabaseHas('operacoes', [
            'referencia_externa' => 'apanha-2026-08-03',
            'custo_real' => 24120,
        ]);

        $campanha = $contexto['campanha']->fresh(['operacoes.produtos', 'custos', 'colheitas']);
        $this->assertSame(24120.0, $campanha->custo_operacoes);
        $this->assertSame(24120.0, $campanha->custo_total_calculado);
    }

    /** Sem custo/hora em lado nenhum, a linha fica a zero e o pedido avisa. */
    public function test_recurso_sem_custo_hora_avisa_em_vez_de_inventar_valor(): void
    {
        $this->autenticarApi();
        $this->criarContexto();

        $maquina = Maquina::query()->create(['nome' => 'Trator sem custo', 'tipo' => 'trator']);

        $response = $this->postJson('/api/v1/trabalhos', [
            'tarefa' => 'Apanha da fruta',
            'campanha' => 'Milho 2026',
            'parcela' => 'Parcela Norte',
            'data_inicio' => '2026-08-03',
            'dias' => 1,
            'incluir_fins_de_semana' => true,
            'horas_por_dia' => 8,
            'numero_pessoas' => 2,
            'valor_hora' => 7.5,
            'maquinas' => [['maquina' => $maquina->id]],
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('entrou a zero', implode(' ', $response->json('avisos')));
        $this->assertDatabaseHas('operacao_recursos', ['maquina_id' => $maquina->id, 'custo_total' => 0]);
        $this->assertSame(0, Custo::query()->where('tipo', 'maquina')->count());
    }

    /** A colheita ligada a operacao de apanha sabe o que custou cada quilo. */
    public function test_colheita_mostra_o_custo_da_apanha_e_o_custo_por_kg(): void
    {
        $contexto = $this->criarContexto();

        $operacao = Operacao::query()->create([
            'campanha_id' => $contexto['campanha']->id,
            'parcela_id' => $contexto['parcela']->id,
            'cultura_id' => $contexto['cultura']->id,
            'tipo' => 'colheita',
            'data_hora_inicio' => '2026-08-03 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 24120,
        ]);

        Custo::query()->create([
            'descricao' => 'Apanha - mao de obra', 'tipo' => 'mao_obra', 'valor' => 18000,
            'data_custo' => '2026-08-03', 'operacao_id' => $operacao->id,
            'campanha_id' => $contexto['campanha']->id,
        ]);
        Custo::query()->create([
            'descricao' => 'Apanha - tratores e transporte', 'tipo' => 'maquina', 'valor' => 6120,
            'data_custo' => '2026-08-03', 'operacao_id' => $operacao->id,
            'campanha_id' => $contexto['campanha']->id,
        ]);

        $colheita = Colheita::query()->create([
            'operacao_id' => $operacao->id,
            'campanha_id' => $contexto['campanha']->id,
            'cultura_id' => $contexto['cultura']->id,
            'parcela_id' => $contexto['parcela']->id,
            'data_colheita' => '2026-08-03',
            'quantidade_total' => 60000,
            'unidade_medida' => 'kg',
            'qualidade' => 'comercial',
        ]);

        $colheita = $colheita->fresh(['operacao']);

        $this->assertSame(24120.0, $colheita->custo_apanha);
        $this->assertSame(0.402, $colheita->custo_por_kg);
        $this->assertSame([
            'mao_obra' => 18000.0,
            'maquinas' => 6120.0,
            'outros' => 0.0,
            'total' => 24120.0,
        ], $colheita->detalheCustoApanha());
    }

    /** Sem operacao ligada nao ha custo, e nao se inventa nenhum. */
    public function test_colheita_sem_operacao_nao_tem_custo(): void
    {
        $contexto = $this->criarContexto();

        $colheita = Colheita::query()->create([
            'campanha_id' => $contexto['campanha']->id,
            'cultura_id' => $contexto['cultura']->id,
            'parcela_id' => $contexto['parcela']->id,
            'data_colheita' => '2026-08-03',
            'quantidade_total' => 60000,
            'unidade_medida' => 'kg',
            'qualidade' => 'comercial',
        ]);

        $this->assertSame(0.0, $colheita->custo_apanha);
        $this->assertSame(0.0, $colheita->custo_por_kg);
    }

    /**
     * O formulario grava o custo escrito a mao e soma-lhe as maquinas. Gravar
     * outra vez tem de dar o mesmo: era aqui que se duplicava o custo.
     */
    public function test_gravar_duas_vezes_nao_duplica_o_custo_das_maquinas(): void
    {
        $contexto = $this->criarContexto();
        $maquina = Maquina::query()->create([
            'nome' => 'Trator Landini', 'tipo' => 'trator', 'custo_hora' => 20,
        ]);

        $operacao = Operacao::query()->create([
            'campanha_id' => $contexto['campanha']->id,
            'parcela_id' => $contexto['parcela']->id,
            'cultura_id' => $contexto['cultura']->id,
            'tipo' => 'mobilização do solo',
            'data_hora_inicio' => '2026-04-02 08:00:00',
            'estado' => 'concluida',
        ]);

        $servico = app(CustoRecursosService::class);
        $linhas = [['maquina' => $maquina->id, 'horas' => 10]];

        // 500 de mao de obra escritos a mao + 10h x 20 EUR = 700.
        foreach ([1, 2] as $gravacao) {
            $servico->sincronizar($operacao->fresh(), $linhas, 1, definirPrincipal: false, custoBase: 500.0);

            $this->assertSame(700.0, (float) $operacao->fresh()->custo_real, "gravacao {$gravacao}");
            $this->assertDatabaseCount('operacao_recursos', 1);
            $this->assertSame(1, Custo::query()->where('tipo', 'maquina')->count());
        }

        // A maquina principal nao e' tocada pelo formulario: e' o formulario que a define.
        $this->assertNull($operacao->fresh()->maquina_id);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['custos:write', 'trabalhos:write', 'colheitas:write']);

        return $user;
    }

    /** @return array{terreno: Terreno, parcela: Parcela, cultura: Cultura, campanha: Campanha} */
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
