<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampanhaGeralTest extends TestCase
{
    use RefreshDatabase;

    public function test_campanha_geral_soma_a_area_das_parcelas_que_cobre(): void
    {
        [$p1, $p2] = $this->criarParcelas();

        $geral = Campanha::query()->create(['nome' => 'Pereiras 2026', 'ano' => 2026, 'data_inicio' => '2026-01-01']);
        $geral->parcelas()->sync([$p1->id, $p2->id]);

        $this->assertTrue($geral->fresh()->ehGeral());
        $this->assertSame(3.0, $geral->fresh()->area_total_ha);
        $this->assertSame('Pereiras 2026', $geral->nome_completo);
    }

    public function test_campanha_antiga_continua_a_derivar_area_e_nome_da_cultura(): void
    {
        [$p1] = $this->criarParcelas();
        $cultura = Cultura::query()->create([
            'parcela_id' => $p1->id, 'nome' => 'Milho', 'tipo' => 'cereal', 'data_plantacao' => '2026-03-01',
        ]);
        $antiga = Campanha::query()->create(['cultura_id' => $cultura->id, 'ano' => 2026, 'data_inicio' => '2026-03-01']);

        $this->assertFalse($antiga->ehGeral());
        $this->assertSame(1.0, $antiga->area_total_ha);
        $this->assertSame('Milho 2026', $antiga->nome_completo);
    }

    public function test_exploracao_lista_os_terrenos_da_campanha_geral(): void
    {
        [$p1, $p2] = $this->criarParcelas();
        $geral = Campanha::query()->create(['nome' => 'Pereiras 2026', 'ano' => 2026, 'data_inicio' => '2026-01-01']);
        $geral->parcelas()->sync([$p1->id, $p2->id]);

        // Os dois terrenos aparecem, sem 'N/A' no caderno de campo.
        $this->assertSame('Terreno A, Terreno B', $geral->fresh()->exploracao_nome);
    }

    public function test_migrar_campanhas_agrupa_por_especie_e_repontar_os_registos(): void
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno A', 'area_total' => 10]);
        $parcelas = collect(['Norte', 'Sul', 'Este'])->map(fn ($n) => Parcela::query()->create([
            'terreno_id' => $terreno->id, 'nome' => $n, 'area_total' => 1,
        ]));

        $culturas = $parcelas->map(fn ($p) => Cultura::query()->create([
            'parcela_id' => $p->id, 'nome' => 'Pereira '.$p->nome, 'tipo' => 'Pereira',
            'data_plantacao' => '2020-01-01',
        ]));

        // Duas das tres tem campanha propria; a terceira nao tem nenhuma.
        $antigas = $culturas->take(2)->map(fn ($c) => Campanha::query()->create([
            'cultura_id' => $c->id, 'ano' => 2026, 'data_inicio' => '2026-01-01',
        ]));

        $operacao = Operacao::query()->create([
            'campanha_id' => $antigas[0]->id, 'parcela_id' => $parcelas[0]->id,
            'tipo' => 'poda', 'data_hora_inicio' => '2026-02-01 08:00:00', 'estado' => 'concluida',
        ]);
        $custo = Custo::query()->create([
            'campanha_id' => $antigas[1]->id, 'descricao' => 'Adubo', 'tipo' => 'material',
            'valor' => 100, 'data_custo' => '2026-02-01',
        ]);

        $this->artisan('agri:migrar-campanhas', ['--ano' => 2026, '--confirmar' => true])
            ->assertSuccessful();

        $geral = Campanha::query()->where('nome', 'Pereiras 2026')->firstOrFail();

        // Cobre as tres parcelas, incluindo a que nao tinha campanha.
        $this->assertCount(3, $geral->parcelas);
        $this->assertSame($geral->id, $operacao->fresh()->campanha_id);
        $this->assertSame($geral->id, $custo->fresh()->campanha_id);

        // As antigas ficam removidas.
        foreach ($antigas as $antiga) {
            $this->assertSoftDeleted('campanhas', ['id' => $antiga->id]);
        }
    }

    public function test_sem_confirmar_nao_altera_nada(): void
    {
        [$p1] = $this->criarParcelas();
        Cultura::query()->create([
            'parcela_id' => $p1->id, 'nome' => 'Pereira Norte', 'tipo' => 'Pereira', 'data_plantacao' => '2020-01-01',
        ]);

        $this->artisan('agri:migrar-campanhas', ['--ano' => 2026])->assertSuccessful();

        $this->assertDatabaseMissing('campanhas', ['nome' => 'Pereiras 2026']);
    }

    public function test_epoca_atravessa_dois_anos_e_apanha_a_poda_do_ano_anterior(): void
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno A', 'area_total' => 10]);
        $parcela = Parcela::query()->create(['terreno_id' => $terreno->id, 'nome' => 'Norte', 'area_total' => 1]);
        Cultura::query()->create([
            'parcela_id' => $parcela->id, 'nome' => 'Pereira Norte', 'tipo' => 'Pereira',
            'data_plantacao' => '2020-01-01',
        ]);

        // Poda feita em Dezembro de 2025, sem campanha atribuída.
        $poda = Operacao::query()->create([
            'parcela_id' => $parcela->id, 'tipo' => 'poda',
            'data_hora_inicio' => '2025-12-10 08:00:00', 'estado' => 'concluida',
        ]);
        // Apanha em Agosto de 2026, também órfã.
        $apanha = Operacao::query()->create([
            'parcela_id' => $parcela->id, 'tipo' => 'colheita',
            'data_hora_inicio' => '2026-08-15 08:00:00', 'estado' => 'concluida',
        ]);
        // Fora da época: pertence à campanha anterior.
        $anterior = Operacao::query()->create([
            'parcela_id' => $parcela->id, 'tipo' => 'poda',
            'data_hora_inicio' => '2025-03-01 08:00:00', 'estado' => 'concluida',
        ]);

        $this->artisan('agri:migrar-campanhas', ['--ano' => 2026, '--confirmar' => true])->assertSuccessful();

        $geral = Campanha::query()->where('nome', 'Pereiras 2026')->firstOrFail();
        $this->assertSame('2025-10-01', $geral->data_inicio->toDateString());
        $this->assertSame('2026-09-30', $geral->data_fim->toDateString());

        $ids = $this->operacoesDoRelatorio($geral);

        $this->assertContains($poda->id, $ids, 'a poda de Dezembro de 2025 pertence à campanha de 2026');
        $this->assertContains($apanha->id, $ids);
        $this->assertNotContains($anterior->id, $ids, 'Março de 2025 é da época anterior');
    }

    /** @return array<int, int> */
    private function operacoesDoRelatorio(Campanha $campanha): array
    {
        $metodo = new \ReflectionMethod(\App\Http\Controllers\CampanhaController::class, 'reportOperationsForGeneralCampaign');
        $metodo->setAccessible(true);

        return $metodo->invoke(app(\App\Http\Controllers\CampanhaController::class), $campanha)
            ->pluck('id')->all();
    }

    /** @return array<int, Parcela> */
    private function criarParcelas(): array
    {
        $a = Terreno::query()->create(['nome' => 'Terreno A', 'area_total' => 10]);
        $b = Terreno::query()->create(['nome' => 'Terreno B', 'area_total' => 10]);

        return [
            Parcela::query()->create(['terreno_id' => $a->id, 'nome' => 'Norte', 'area_total' => 1]),
            Parcela::query()->create(['terreno_id' => $b->id, 'nome' => 'Sul', 'area_total' => 2]),
        ];
    }
}
