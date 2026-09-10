<?php

namespace Tests\Feature;

use App\Models\Despesa;
use App\Models\Produto;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Services\PaperInvoice\CategoriaDaFatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uma fatura carregada no ecra tem de deixar o stock em dia sozinha.
 *
 * Antes, uma linha so entrava em stock se ja estivesse ligada a um produto do
 * catalogo, e o ecra nunca criava produtos - resultado: a despesa ficava
 * registada e o stock nao mexia.
 */
class StockAutomaticoFaturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fatura_de_adubos_cria_o_produto_e_da_entrada_em_stock(): void
    {
        $this->autenticar();

        $this->post(route('app.despesas.store'), [
            'titulo' => 'AgroExclusive',
            'numero_fatura' => 'FT 1000/1755',
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-08-11',
            'categoria' => 'fertilizantes',
            'items' => [[
                'descricao' => 'STOOP CA-B 25 kg',
                'quantidade' => 2,
                'preco_unitario' => 50,
                'iva_percentagem' => 6,
            ]],
        ])->assertRedirect();

        $produto = Produto::query()->firstOrFail();

        $this->assertSame('STOOP CA-B 25 kg', $produto->nome);
        $this->assertSame('fertilizante', $produto->tipo);
        $this->assertSame('kg', $produto->unidade_medida);
        $this->assertSame(25.0, (float) $produto->conteudo);
        // 50 EUR o saco de 25 kg = 2 EUR/kg.
        $this->assertSame(2.0, (float) $produto->custo_unitario);
        // Dois sacos de 25 kg = 50 kg.
        $this->assertSame(50.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }

    /** Pecas sao despesa, nao inventario: nao enchem o catalogo. */
    public function test_fatura_de_pecas_nao_cria_produtos(): void
    {
        $this->autenticar();

        $this->post(route('app.despesas.store'), [
            'titulo' => 'Agribipes',
            'numero_fatura' => 'FR 2026A101/3403',
            'fornecedor' => 'Agribipes',
            'data' => '2026-09-09',
            'categoria' => 'pecas',
            'items' => [[
                'descricao' => 'TUBO BORRACHA RETORNO OLEO 2TE - 1/2"',
                'quantidade' => 2,
                'preco_unitario' => 15,
                'iva_percentagem' => 23,
            ]],
        ])->assertRedirect();

        $this->assertSame(0, Produto::query()->count());
        $this->assertSame(0, Stock::query()->count());
        $this->assertSame(1, Despesa::query()->count());
    }

    /** Conformidade DGAV: um fitofarmaco sem autorizacao nao entra no catalogo. */
    public function test_fitofarmaco_sem_dgav_nao_entra_em_stock_e_avisa(): void
    {
        $this->autenticar();

        $this->post(route('app.despesas.store'), [
            'titulo' => 'Casa Queridos',
            'numero_fatura' => 'FT 1100/1',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'items' => [
                ['descricao' => 'BANJO fluziname - 5 LT (AV 1199)', 'quantidade' => 2, 'preco_unitario' => 164.90, 'iva_percentagem' => 6],
                ['descricao' => 'Produto sem autorizacao 5 LT', 'quantidade' => 1, 'preco_unitario' => 20, 'iva_percentagem' => 6],
            ],
        ])->assertRedirect()->assertSessionHas('success', fn (string $msg) => str_contains($msg, 'DGAV'));

        $this->assertSame(1, Produto::query()->count());

        $produto = Produto::query()->firstOrFail();
        $this->assertSame('AV 1199', $produto->numero_autorizacao_dgav);
        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }

    /** Um produto que ja exista no catalogo liga-se; nao se duplica. */
    public function test_produto_existente_e_reaproveitado(): void
    {
        $this->autenticar();

        $produto = Produto::query()->create([
            'nome' => 'STOOP CA-B 25 kg', 'tipo' => 'fertilizante',
            'unidade_medida' => 'kg', 'conteudo' => 25,
        ]);

        $this->post(route('app.despesas.store'), [
            'titulo' => 'AgroExclusive', 'data' => '2026-08-11', 'categoria' => 'fertilizantes',
            'items' => [['descricao' => 'stoop ca-b 25 KG', 'quantidade' => 1, 'preco_unitario' => 50, 'iva_percentagem' => 6]],
        ])->assertRedirect();

        $this->assertSame(1, Produto::query()->count());
        $this->assertSame(25.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }

    public function test_adivinha_a_categoria_da_fatura(): void
    {
        $this->assertSame('fitofarmaceuticos', CategoriaDaFatura::adivinhar('Casa Queridos', ['BANJO fluziname - 5 LT']));
        // O número DGAV numa linha decide, seja qual for o fornecedor.
        $this->assertSame('fitofarmaceuticos', CategoriaDaFatura::adivinhar('Loja X', ['ERUNE 5 LT (AV 1761)']));
        $this->assertSame('fertilizantes', CategoriaDaFatura::adivinhar('AgroExclusive', ['STOOP CA-B 25 kg']));
        $this->assertSame('fertilizantes', CategoriaDaFatura::adivinhar('Loja X', ['Adubo foliar 20 L']));
        $this->assertSame('pecas', CategoriaDaFatura::adivinhar('Agribipes', ['TUBO BORRACHA RETORNO OLEO 2TE - 1/2"']));
        $this->assertSame('combustivel', CategoriaDaFatura::adivinhar('Posto', ['Gasóleo agrícola']));
        $this->assertNull(CategoriaDaFatura::adivinhar('Loja X', ['Artigo qualquer']));
    }

    private function autenticar(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));

        $this->actingAs($user);

        return $user;
    }
}
