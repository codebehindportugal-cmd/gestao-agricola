<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Colheita;
use App\Models\Cultura;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Receita;
use App\Models\Terreno;
use App\Services\ResumoPorEspecieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A campanha única repartida por espécie — pereira, macieira.
 */
class ResumoPorEspecieTest extends TestCase
{
    use RefreshDatabase;

    public function test_separa_tratamentos_custos_quilos_e_margem_por_especie(): void
    {
        $cenario = $this->cenario();

        $resumo = app(ResumoPorEspecieService::class)->paraCampanha($cenario['campanha']);

        $porEspecie = collect($resumo['especies'])->keyBy('especie');

        $this->assertTrue($porEspecie->has('Pereira'));
        $this->assertTrue($porEspecie->has('Macieira'));

        $pereira = $porEspecie['Pereira'];
        $macieira = $porEspecie['Macieira'];

        // Dois tratamentos nas pereiras, um nas macieiras.
        $this->assertSame(2, $pereira['tratamentos']);
        $this->assertSame(1, $macieira['tratamentos']);

        $this->assertSame(10000.0, $pereira['kg']);
        $this->assertSame(2000.0, $macieira['kg']);

        // Custo próprio: 1000 nas pereiras, 200 nas macieiras. O custo geral de
        // 600 € reparte-se pelos quilos — 10000/12000 e 2000/12000.
        $this->assertSame(500.0, $pereira['custo_rateado']);
        $this->assertSame(100.0, $macieira['custo_rateado']);

        $this->assertSame(1500.0, $pereira['custo_total']);
        $this->assertSame(300.0, $macieira['custo_total']);

        // 3000 € de venda de peras, 10000 kg colhidos.
        $this->assertSame(3000.0, $pereira['vendas']);
        $this->assertSame(1500.0, $pereira['margem']);
        $this->assertSame(0.15, $pereira['custo_kg']);

        // A soma das linhas é o total da campanha.
        $this->assertSame(1800.0, $resumo['total']['custo_total']);
        $this->assertSame(12000.0, $resumo['total']['kg']);
        $this->assertSame(
            round($pereira['custo_total'] + $macieira['custo_total'], 2),
            $resumo['total']['custo_total']
        );
    }

    public function test_registo_sem_cultura_nem_parcela_fica_numa_linha_a_parte(): void
    {
        $cenario = $this->cenario();

        // Uma operação sem parcela nem cultura: não dá para adivinhar de quem é.
        Operacao::query()->create([
            'campanha_id' => $cenario['campanha']->id,
            'tipo' => 'Tratamento Fitossanitário',
            'data_hora_inicio' => '2026-05-01 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 50,
        ]);

        $resumo = app(ResumoPorEspecieService::class)->paraCampanha($cenario['campanha']->fresh());

        $semEspecie = collect($resumo['especies'])->firstWhere('especie', ResumoPorEspecieService::SEM_ESPECIE);

        $this->assertNotNull($semEspecie, 'a linha "Sem espécie" tem de aparecer, para se ver o que falta corrigir');
        $this->assertSame(1, $semEspecie['tratamentos']);

        // E vai para o fim da lista: é para corrigir, não para ler.
        $especies = $resumo['especies'];
        $this->assertSame(ResumoPorEspecieService::SEM_ESPECIE, end($especies)['especie']);
    }

    /** @return array<string, mixed> */
    private function cenario(): array
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno A', 'area_total' => 10]);

        $parcelaPereiras = Parcela::query()->create(['terreno_id' => $terreno->id, 'nome' => 'Torre', 'area_total' => 3]);
        $parcelaMacieiras = Parcela::query()->create(['terreno_id' => $terreno->id, 'nome' => 'Goldes', 'area_total' => 1]);

        $pereira = Cultura::query()->create([
            'parcela_id' => $parcelaPereiras->id, 'nome' => 'Pereira Torre', 'tipo' => 'Pereira',
            'data_plantacao' => '2020-01-01',
        ]);
        $macieira = Cultura::query()->create([
            'parcela_id' => $parcelaMacieiras->id, 'nome' => 'Macieira Goldes', 'tipo' => 'Macieira',
            'data_plantacao' => '2020-01-01',
        ]);

        $campanha = Campanha::query()->create([
            'nome' => '2025/2026', 'ano' => 2026,
            'data_inicio' => '2025-10-01', 'data_fim' => '2026-09-30', 'status' => 'em_curso',
        ]);
        $campanha->parcelas()->sync([$parcelaPereiras->id, $parcelaMacieiras->id]);

        foreach ([[$pereira, 600.0], [$pereira, 400.0], [$macieira, 200.0]] as [$cultura, $custo]) {
            Operacao::query()->create([
                'campanha_id' => $campanha->id,
                'cultura_id' => $cultura->id,
                'parcela_id' => $cultura->parcela_id,
                'tipo' => 'Tratamento Fitossanitário',
                'data_hora_inicio' => '2026-05-01 08:00:00',
                'estado' => 'concluida',
                'custo_real' => $custo,
            ]);
        }

        Colheita::query()->create([
            'campanha_id' => $campanha->id, 'cultura_id' => $pereira->id, 'parcela_id' => $parcelaPereiras->id,
            'data_colheita' => '2026-08-20', 'quantidade_total' => 10000, 'unidade_medida' => 'kg',
        ]);
        Colheita::query()->create([
            'campanha_id' => $campanha->id, 'cultura_id' => $macieira->id, 'parcela_id' => $parcelaMacieiras->id,
            'data_colheita' => '2026-09-10', 'quantidade_total' => 2000, 'unidade_medida' => 'kg',
        ]);

        Receita::query()->create([
            'descricao' => 'Venda de peras', 'tipo' => 'venda_colheita', 'valor' => 3000,
            'quantidade' => 10000, 'unidade' => 'kg', 'data' => '2026-09-01',
            'campanha_id' => $campanha->id, 'cultura_id' => $pereira->id, 'parcela_id' => $parcelaPereiras->id,
        ]);

        // Gasto da exploração inteira: sem cultura nem parcela, vai a rateio.
        Custo::query()->create([
            'descricao' => 'Luz das regas', 'tipo' => 'energia', 'valor' => 600,
            'data_custo' => '2026-07-15', 'campanha_id' => $campanha->id,
        ]);

        return compact('campanha', 'pereira', 'macieira');
    }
}
