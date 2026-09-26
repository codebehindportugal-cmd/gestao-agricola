<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * agri:unificar-campanhas — uma campanha por época, em vez de uma por espécie.
 */
class UnificarCampanhasTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_confirmar_nao_altera_nada(): void
    {
        $this->cenario();

        $this->artisan('agri:unificar-campanhas')->assertSuccessful();

        $this->assertDatabaseMissing('campanhas', ['nome' => '2025/2026']);
        $this->assertDatabaseHas('campanhas', ['nome' => 'Pereiras 2026', 'deleted_at' => null]);
    }

    public function test_junta_as_campanhas_da_epoca_e_reponta_os_registos(): void
    {
        ['pereiras' => $pereiras, 'macieiras' => $macieiras, 'antiga' => $antiga,
            'operacao' => $operacao, 'custo' => $custo, 'parcelas' => $parcelas] = $this->cenario();

        $this->artisan('agri:unificar-campanhas', ['--confirmar' => true])->assertSuccessful();

        $unica = Campanha::query()->where('nome', '2025/2026')->firstOrFail();

        $this->assertSame('2025-10-01', $unica->data_inicio->toDateString());
        $this->assertSame('2026-09-30', $unica->data_fim->toDateString());
        $this->assertTrue($unica->ehGeral());

        $this->assertSame($unica->id, $operacao->fresh()->campanha_id);
        $this->assertSame($unica->id, $custo->fresh()->campanha_id);

        // Cobre as parcelas das três campanhas, sem repetir nenhuma.
        $this->assertEqualsCanonicalizing(
            $parcelas->pluck('id')->all(),
            $unica->parcelas()->pluck('parcelas.id')->all()
        );

        foreach ([$pereiras, $macieiras, $antiga] as $campanha) {
            $this->assertSoftDeleted('campanhas', ['id' => $campanha->id]);
        }
    }

    public function test_custos_e_despesas_sem_campanha_passam_para_a_epoca(): void
    {
        $this->cenario();

        $partilhado = Custo::query()->create([
            'descricao' => 'Luz das regas', 'tipo' => 'energia', 'valor' => 400,
            'data_custo' => '2026-07-15', 'rateavel' => true, 'base_rateio' => 'kg',
        ]);
        $despesa = Despesa::query()->create([
            'titulo' => 'Casa Queridos FT 1100/135811', 'valor' => 1212.75, 'data' => '2026-07-15',
            'categoria' => 'fitofarmaceuticos',
        ]);
        // Fora da época: fica como está.
        $antigo = Custo::query()->create([
            'descricao' => 'Seguro 2024', 'tipo' => 'outro', 'valor' => 100,
            'data_custo' => '2024-05-01', 'rateavel' => true,
        ]);

        $this->artisan('agri:unificar-campanhas', ['--confirmar' => true])->assertSuccessful();

        $unica = Campanha::query()->where('nome', '2025/2026')->firstOrFail();

        $this->assertSame($unica->id, $partilhado->fresh()->campanha_id);
        $this->assertSame($unica->id, $despesa->fresh()->campanha_id);
        $this->assertNull($antigo->fresh()->campanha_id);

        // A marca fica: é dela que vive o rateio por cultura/parcela dentro da
        // campanha, quando existir.
        $this->assertTrue((bool) $partilhado->fresh()->rateavel);
    }

    public function test_apagar_so_tira_as_campanhas_que_a_aplicacao_inventou(): void
    {
        ['pereiras' => $pereiras, 'antiga' => $inventada] = $this->cenario();

        $this->artisan('agri:unificar-campanhas', ['--confirmar' => true, '--apagar' => true])
            ->assertSuccessful();

        // A inventada (sem nome, agarrada a uma cultura, sem fim) desaparece
        // mesmo — nem com withTrashed volta.
        $this->assertDatabaseMissing('campanhas', ['id' => $inventada->id]);

        // A "Pereiras 2026" foi criada de propósito: fica arquivada.
        $this->assertSoftDeleted('campanhas', ['id' => $pereiras->id]);

        $this->assertDatabaseHas('campanhas', ['nome' => '2025/2026']);
    }

    public function test_custo_de_pecas_de_uma_maquina_nao_e_absorvido(): void
    {
        $this->cenario();

        $verde = Maquina::query()->create(['nome' => 'Verde', 'tipo' => 'trator']);

        // Fica sem campanha, como a API a grava, mas não é um gasto geral:
        // pertence àquele tractor e não tem de ir para a campanha.
        $peca = Custo::query()->create([
            'descricao' => 'Radiador', 'tipo' => 'manutencao', 'valor' => 470,
            'data_custo' => '2026-07-24', 'rateavel' => false, 'maquina_id' => $verde->id,
        ]);
        $despesa = Despesa::query()->create([
            'titulo' => 'Hencaros FR.2026/166', 'valor' => 578.10, 'data' => '2026-07-24',
            'categoria' => 'pecas', 'maquina_id' => $verde->id,
        ]);

        $this->artisan('agri:unificar-campanhas', ['--confirmar' => true])->assertSuccessful();

        $this->assertNull($peca->fresh()->campanha_id);
        $this->assertNull($despesa->fresh()->campanha_id);
    }

    public function test_manter_rateaveis_deixa_os_custos_partilhados_como_estao(): void
    {
        $this->cenario();

        $partilhado = Custo::query()->create([
            'descricao' => 'Luz das regas', 'tipo' => 'energia', 'valor' => 400,
            'data_custo' => '2026-07-15', 'rateavel' => true, 'base_rateio' => 'kg',
        ]);

        $this->artisan('agri:unificar-campanhas', ['--confirmar' => true, '--manter-rateaveis' => true])
            ->assertSuccessful();

        $this->assertNull($partilhado->fresh()->campanha_id);
    }

    /** @return array<string, mixed> */
    private function cenario(): array
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno A', 'area_total' => 10]);

        $parcelas = collect(['Norte', 'Sul', 'Este'])->map(fn ($nome) => Parcela::query()->create([
            'terreno_id' => $terreno->id, 'nome' => $nome, 'area_total' => 1,
        ]));

        $pereiras = Campanha::query()->create([
            'nome' => 'Pereiras 2026', 'ano' => 2026,
            'data_inicio' => '2025-10-01', 'data_fim' => '2026-09-30',
        ]);
        $pereiras->parcelas()->sync([$parcelas[0]->id, $parcelas[1]->id]);

        $macieiras = Campanha::query()->create([
            'nome' => 'Macieiras 2026', 'ano' => 2026,
            'data_inicio' => '2025-10-01', 'data_fim' => '2026-09-30',
        ]);
        // Partilha a parcela Sul: um UPDATE cego em campanha_parcela rebentava
        // aqui com a chave única.
        $macieiras->parcelas()->sync([$parcelas[1]->id]);

        // Campanha antiga, por cultura e sem fim — como a "Cimeira 1 2026".
        $cultura = Cultura::query()->create([
            'parcela_id' => $parcelas[2]->id, 'nome' => 'Cimeira 1', 'tipo' => 'Pereira',
            'data_plantacao' => '2020-01-01',
        ]);
        $antiga = Campanha::query()->create([
            'cultura_id' => $cultura->id, 'ano' => 2026, 'data_inicio' => '2026-01-01',
        ]);

        $operacao = Operacao::query()->create([
            'campanha_id' => $pereiras->id, 'parcela_id' => $parcelas[0]->id,
            'tipo' => 'poda', 'data_hora_inicio' => '2026-02-01 08:00:00', 'estado' => 'concluida',
        ]);

        $custo = Custo::query()->create([
            'campanha_id' => $antiga->id, 'descricao' => 'Adubo', 'tipo' => 'material',
            'valor' => 100, 'data_custo' => '2026-02-01',
        ]);

        return compact('pereiras', 'macieiras', 'antiga', 'operacao', 'custo', 'parcelas');
    }
}
