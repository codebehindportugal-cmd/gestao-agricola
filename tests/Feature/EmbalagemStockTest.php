<?php

namespace Tests\Feature;

use App\Models\Despesa;
use App\Models\MovimentoStock;
use App\Models\Produto;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Services\MovimentoStockService;
use App\Services\PaperInvoice\TamanhoEmbalagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A fatura conta embalagens e o campo conta produto.
 *
 * "BANJO fluziname - 5 LT", quantidade 2, sao 10 litros de stock e nao 2
 * unidades - e o custo e' 32,98 EUR por litro, nao 164,90.
 */
class EmbalagemStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_o_tamanho_da_embalagem_da_designacao(): void
    {
        $casos = [
            'BANJO fluziname - 5 LT ( AV 1199 )' => [5.0, 'L'],
            'ERUNE primetanil - 5 LT' => [5.0, 'L'],
            'Adubo foliar 20L' => [20.0, 'L'],
            'Nitrato de cálcio 25 kg' => [25.0, 'kg'],
            'Semente de milho 50 doses' => [50.0, 'doses'],
            'Óleo mineral 500 ml' => [0.5, 'L'],
            'Herbicida 250g' => [0.25, 'kg'],
        ];

        foreach ($casos as $descricao => [$conteudo, $unidade]) {
            $lido = TamanhoEmbalagem::daDescricao($descricao);

            $this->assertNotNull($lido, "não leu o tamanho de: {$descricao}");

            $base = TamanhoEmbalagem::paraUnidadeBase($lido['conteudo'], $lido['unidade']);

            $this->assertSame($conteudo, $base['conteudo'], "conteúdo errado em: {$descricao}");
            $this->assertSame($unidade, $base['unidade'], "unidade errada em: {$descricao}");
        }
    }

    /** Uma designacao sem tamanho nao pode inventar um. */
    public function test_sem_tamanho_na_designacao_devolve_nada(): void
    {
        $this->assertNull(TamanhoEmbalagem::daDescricao('Adubo 20-20-20'));
        $this->assertNull(TamanhoEmbalagem::daDescricao('Serviço de aplicação'));
    }

    public function test_o_stock_sobe_o_conteudo_e_nao_o_numero_de_embalagens(): void
    {
        $produto = Produto::query()->create([
            'nome' => 'BANJO fluziname',
            'tipo' => 'fitofarmaco',
            'unidade_medida' => 'L',
            'conteudo' => 5,
        ]);

        $despesa = Despesa::query()->create([
            'titulo' => 'Casa Queridos',
            'numero_fatura' => 'FT 1100/132690',
            'valor' => 349.59,
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
        ]);

        $despesa->items()->create([
            'descricao' => 'BANJO fluziname - 5 LT (AV 1199)',
            'quantidade' => 2,
            'preco_unitario' => 164.9,
            'iva_percentagem' => 6,
            'produto_id' => $produto->id,
        ]);

        app(MovimentoStockService::class)->processarEntradas($despesa->load('items.produto'));

        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));

        $movimento = MovimentoStock::query()->firstOrFail();
        $this->assertSame(10.0, (float) $movimento->quantidade);
        // 164,90 por bidão de 5 L = 32,98 por litro.
        $this->assertSame(32.98, (float) $movimento->custo_unitario);
    }

    /** Sem tamanho registado, tudo se mantem como estava: uma compra e' uma unidade. */
    public function test_produto_sem_conteudo_continua_a_contar_unidades(): void
    {
        $produto = Produto::query()->create(['nome' => 'Corda', 'tipo' => 'outro', 'unidade_medida' => 'un']);

        $despesa = Despesa::query()->create([
            'titulo' => 'Loja', 'valor' => 30, 'data' => '2026-04-10', 'categoria' => 'outro',
        ]);
        $despesa->items()->create([
            'descricao' => 'Corda', 'quantidade' => 3, 'preco_unitario' => 10,
            'iva_percentagem' => 23, 'produto_id' => $produto->id,
        ]);

        app(MovimentoStockService::class)->processarEntradas($despesa->load('items.produto'));

        $this->assertSame(3.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
        $this->assertSame(10.0, (float) MovimentoStock::query()->value('custo_unitario'));
    }

    /** O produto criado pela fatura ja nasce a saber quanto leva a embalagem. */
    public function test_a_api_cria_o_produto_com_o_tamanho_da_embalagem(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));
        Sanctum::actingAs($user, ['faturas:write', 'custos:write']);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132690',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'descricao' => 'BANJO fluziname - 5 LT (AV 1199)',
                'quantidade' => 2,
                'preco_unitario' => 164.9,
                'iva_percentagem' => 6,
                'numero_autorizacao_dgav' => 'AV 1199',
                'tipo_produto' => 'fitofarmaco',
            ]],
        ])->assertCreated();

        $produto = Produto::query()->firstOrFail();

        $this->assertSame(5.0, (float) $produto->conteudo);
        $this->assertSame('L', $produto->unidade_medida);
        $this->assertSame(32.98, (float) $produto->custo_unitario);
        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }
}
