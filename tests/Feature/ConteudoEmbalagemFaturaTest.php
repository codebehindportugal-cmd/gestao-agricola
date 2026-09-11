<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O tamanho da embalagem e da linha da fatura, nao do catalogo.
 *
 * O ERUNE entrou mal porque o produto ja existia com conteudo 1: a designacao
 * dizia 5 LT e o servidor confiou no que estava gravado. Dois bidoes deram 2
 * unidades em vez de 10 litros, e o custo ficou 103,50 em vez de 20,70 por
 * litro.
 */
class ConteudoEmbalagemFaturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_conteudo_embalagem_do_pedido_ganha_a_tudo(): void
    {
        $this->autenticarApi();

        // Catalogo diz 1 L, designacao nao diz nada: quem le a fatura ve o papel.
        $produto = Produto::query()->create([
            'nome' => 'ERUNE primetanil',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 1761',
            'unidade_medida' => 'L',
            'conteudo' => 1,
        ]);

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132700',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'produto' => 'ERUNE primetanil',
                'descricao' => 'ERUNE primetanil',
                'conteudo_embalagem' => 5,
                'unidade_embalagem' => 'L',
                'quantidade' => 2,
                'preco_unitario' => 103.50,
                'iva_percentagem' => 6,
            ]],
        ]);

        $response->assertCreated();

        // A resposta tem de chegar para confirmar os 10 L a 20,70 EUR/L sem ir
        // a listagem do stock.
        $movimento = $response->json('dados.movimentos_stock.0');
        $this->assertSame(10.0, (float) $movimento['quantidade_base']);
        $this->assertSame('L', $movimento['unidade']);
        $this->assertSame(20.7, (float) $movimento['custo_por_unidade_base']);
        $this->assertSame(5.0, (float) $movimento['conteudo_embalagem']);

        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
        $this->assertSame(5.0, (float) $produto->fresh()->conteudo);
        $this->assertSame(20.7, (float) $produto->fresh()->custo_unitario);
    }

    public function test_le_o_tamanho_da_designacao_mesmo_em_produto_existente(): void
    {
        $this->autenticarApi();

        $produto = Produto::query()->create([
            'nome' => 'ERUNE primetanil',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 1761',
            'unidade_medida' => 'L',
            'conteudo' => 1,
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132701',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'produto' => 'ERUNE primetanil',
                'descricao' => 'ERUNE primetanil - 5 LT ( AV 1761 )',
                'quantidade' => 2,
                'preco_unitario' => 103.50,
                'iva_percentagem' => 6,
            ]],
        ])->assertCreated();

        // Conteudo 1 e o valor de antes desta coluna existir: corrige-se.
        $this->assertSame(5.0, (float) $produto->fresh()->conteudo);
        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }

    public function test_conteudo_diferente_do_catalogo_usa_o_da_fatura_e_avisa(): void
    {
        $this->autenticarApi();

        // Ele costuma comprar o bidao de 20 L; esta fatura traz o de 5 L.
        $produto = Produto::query()->create([
            'nome' => 'BANJO fluziname',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 1199',
            'unidade_medida' => 'L',
            'conteudo' => 20,
        ]);

        $response = $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132702',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'produto' => 'BANJO fluziname',
                'descricao' => 'BANJO fluziname - 5 LT ( AV 1199 )',
                'quantidade' => 2,
                'preco_unitario' => 164.90,
                'iva_percentagem' => 6,
            ]],
        ])->assertCreated();

        $avisos = $response->json('avisos');
        $this->assertTrue(
            collect($avisos)->contains(fn ($aviso) => str_contains($aviso, 'difere da fatura')),
            'tem de avisar que o catálogo e a fatura não dizem o mesmo: '.json_encode($avisos)
        );

        // A entrada usa o da fatura; o catalogo nao se mexe sozinho.
        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
        $this->assertSame(20.0, (float) $produto->fresh()->conteudo);
    }

    public function test_produto_encontrado_pelo_nome_nao_cria_duplicado(): void
    {
        $this->autenticarApi();

        $produto = Produto::query()->create([
            'nome' => 'BANJO fluziname',
            'tipo' => 'fitofarmaco',
            'unidade_medida' => 'L',
            'conteudo' => 5,
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132703',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'produto' => 'BANJO fluziname - 5 LT ( AV 1199 )',
                'codigo' => '7788',
                'numero_autorizacao_dgav' => 'AV 1199',
                'descricao' => 'BANJO fluziname - 5 LT ( AV 1199 )',
                'quantidade' => 2,
                'preco_unitario' => 164.90,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fitofarmaco',
            ]],
        ])->assertCreated();

        $this->assertSame(1, Produto::query()->count(), 'o Banjo entrou duas vezes no catálogo');

        // E fica com as referencias gravadas, para a fatura seguinte o apanhar
        // logo pelo codigo.
        $fresco = $produto->fresh();
        $this->assertSame('7788', $fresco->codigo_interno);
        $this->assertSame('AV 1199', $fresco->numero_autorizacao_dgav);
    }

    public function test_produto_encontrado_pelo_dgav_quando_o_codigo_falha(): void
    {
        $this->autenticarApi();

        $produto = Produto::query()->create([
            'nome' => 'Montana',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 2222',
            'unidade_medida' => 'L',
            'conteudo' => 5,
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132704',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'codigo' => '991133',
                'numero_autorizacao_dgav' => 'AV 2222',
                'descricao' => 'MONTANA tebuconazol 5 LT',
                'quantidade' => 1,
                'preco_unitario' => 60,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fitofarmaco',
            ]],
        ])->assertCreated();

        $this->assertSame(1, Produto::query()->count());
        $this->assertSame('991133', $produto->fresh()->codigo_interno);
    }

    /** A primeira palavra igual nao chega: "Adubo Cálcio" nao e "Adubo Foliar X". */
    public function test_nome_parecido_nao_liga_produtos_diferentes(): void
    {
        $this->autenticarApi();

        Produto::query()->create([
            'nome' => 'Adubo Foliar X',
            'tipo' => 'fertilizante',
            'unidade_medida' => 'kg',
            'conteudo' => 20,
        ]);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/132705',
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-04-10',
            'categoria' => 'fertilizantes',
            'linhas' => [[
                'produto' => 'Adubo Cálcio',
                'descricao' => 'Adubo Cálcio 25 kg',
                'quantidade' => 1,
                'preco_unitario' => 40,
                'iva_percentagem' => 6,
                'tipo_produto' => 'fertilizante',
            ]],
        ])->assertCreated();

        $this->assertSame(2, Produto::query()->count(), 'ligou dois adubos diferentes ao mesmo produto');
    }

    /** O mesmo pelo ecrã: o ERUNE ja existia com conteudo 1 e entrou a 1 L. */
    public function test_o_ecra_tambem_le_o_tamanho_da_designacao(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));
        $this->actingAs($user);

        $produto = Produto::query()->create([
            'nome' => 'ERUNE primetanil - 5 LT ( AV 1761 )',
            'tipo' => 'fitofarmaco',
            'numero_autorizacao_dgav' => 'AV 1761',
            'unidade_medida' => 'L',
            'conteudo' => 1,
        ]);

        $this->post(route('app.despesas.store'), [
            'titulo' => 'Casa Queridos',
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
            'items' => [[
                'descricao' => 'ERUNE primetanil - 5 LT ( AV 1761 )',
                'quantidade' => 2,
                'preco_unitario' => 103.50,
                'iva_percentagem' => 6,
                'produto_id' => $produto->id,
            ]],
        ])->assertRedirect();

        $this->assertSame(10.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
        $this->assertSame(5.0, (float) $produto->fresh()->conteudo);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'operador']));

        Sanctum::actingAs($user, ['faturas:write', 'custos:write']);

        return $user;
    }
}
