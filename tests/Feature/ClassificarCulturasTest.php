<?php

namespace Tests\Feature;

use App\Models\Cultura;
use App\Models\Parcela;
use App\Models\Terreno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificarCulturasTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifica_pela_parcela_e_nao_pelo_nome_da_cultura(): void
    {
        $parcela = $this->parcela('Cumeira 1', 'Cumeira');

        // Nome da cultura diferente do de produção, de propósito: a chave é a
        // parcela, por isso tem de ser classificada na mesma.
        $cultura = $this->cultura($parcela, 'Um nome qualquer', 'pomar');

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(['Pereira', 'Rocha'], [$cultura->fresh()->tipo, $cultura->fresh()->variedade]);
    }

    public function test_desambigua_as_duas_parcelas_chamadas_casa(): void
    {
        $pereiras = $this->parcela('Pereiras - Casa', 'Casa');
        $pessegueiros = $this->parcela('Casa Pessegueiros', 'Casa');

        $daPereira = $this->cultura($pereiras, 'Casa', 'pomar');
        $doPessegueiro = $this->cultura($pessegueiros, 'Casa', 'pomar');

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame('Pereira', $daPereira->fresh()->tipo);
        // A Casa dos pessegueiros não consta da tabela e não pode ser tocada.
        $this->assertSame('pomar', $doPessegueiro->fresh()->tipo);
    }

    public function test_cria_as_duas_variedades_do_choupinho_na_mesma_parcela(): void
    {
        $choupinho = $this->parcela('Choupinho', 'Choupinho');

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(2, Cultura::query()->where('parcela_id', $choupinho->id)->count());
        $this->assertDatabaseHas('culturas', ['nome' => 'Choupinho Fuji', 'variedade' => 'Fuji', 'tipo' => 'Macieira']);
        $this->assertDatabaseHas('culturas', ['nome' => 'Choupinho Royal Gala', 'variedade' => 'Royal Gala']);
    }

    public function test_macieiras_ficam_com_a_variedade_certa(): void
    {
        $troncos = $this->cultura($this->parcela('Troncos - Maceiras', 'Troncos'), 'Troncos', 'pomar');
        $torre = $this->cultura($this->parcela('Torre - Maceiras', 'Torre'), 'Torre', 'pomar');

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(['Macieira', 'Royal Gala'], [$troncos->fresh()->tipo, $troncos->fresh()->variedade]);
        $this->assertSame(['Macieira', 'Jonagold Red'], [$torre->fresh()->tipo, $torre->fresh()->variedade]);
    }

    public function test_sem_confirmar_nao_altera_nada(): void
    {
        $cultura = $this->cultura($this->parcela('Cumeira 1', 'Cumeira'), 'Cumeira', 'pomar');

        $this->artisan('agri:classificar-culturas')->assertSuccessful();

        $this->assertSame('pomar', $cultura->fresh()->tipo);
    }

    public function test_e_idempotente(): void
    {
        $choupinho = $this->parcela('Choupinho', 'Choupinho');

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();
        $this->artisan('agri:classificar-culturas', ['--confirmar' => true])->assertSuccessful();

        $this->assertSame(2, Cultura::query()->where('parcela_id', $choupinho->id)->count());
    }

    public function test_perenes_corrige_o_ciclo_produtivo(): void
    {
        $cultura = $this->cultura($this->parcela('Cumeira 1', 'Cumeira'), 'Cumeira', 'pomar');

        // O default 'anual' é da base de dados: em memória o modelo acabado de
        // criar tem null, por isso lê-se do disco.
        $this->assertSame('anual', $cultura->fresh()->ciclo_produtivo);

        $this->artisan('agri:classificar-culturas', ['--confirmar' => true, '--perenes' => true])->assertSuccessful();

        $this->assertSame('perene', $cultura->fresh()->ciclo_produtivo);
    }

    private function parcela(string $terreno, string $parcela): Parcela
    {
        $t = Terreno::query()->firstOrCreate(['nome' => $terreno], ['area_total' => 10]);

        return Parcela::query()->create(['terreno_id' => $t->id, 'nome' => $parcela, 'area_total' => 1]);
    }

    private function cultura(Parcela $parcela, string $nome, string $tipo): Cultura
    {
        return Cultura::query()->create([
            'parcela_id' => $parcela->id, 'nome' => $nome, 'tipo' => $tipo,
            'data_plantacao' => '2020-01-01',
        ]);
    }
}
