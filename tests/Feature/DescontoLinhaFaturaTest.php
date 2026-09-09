<?php

namespace Tests\Feature;

use App\Models\Despesa;
use App\Models\FaturaItem;
use App\Models\Produto;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Services\MovimentoStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os fornecedores descontam por artigo. Sem o desconto registado, o total da
 * despesa nao batia certo com a fatura e o preco que entrava em stock era o de
 * tabela, nao o que se pagou.
 */
class DescontoLinhaFaturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_desconto_entra_antes_do_iva(): void
    {
        $item = new FaturaItem([
            'descricao' => 'Adubo foliar 20L',
            'quantidade' => 2,
            'preco_unitario' => 100,
            'desconto_percentagem' => 10,
            'iva_percentagem' => 6,
        ]);

        $this->assertSame(200.0, $item->total_bruto);
        $this->assertSame(20.0, $item->desconto_valor);
        $this->assertSame(180.0, $item->total_sem_iva);
        $this->assertSame(10.8, $item->total_iva_valor);
        $this->assertSame(190.8, $item->total_com_iva);
        $this->assertSame(90.0, $item->preco_liquido);
    }

    public function test_sem_desconto_as_contas_ficam_como_estavam(): void
    {
        $item = new FaturaItem([
            'quantidade' => 4,
            'preco_unitario' => 45,
            'iva_percentagem' => 23,
        ]);

        $this->assertSame(180.0, $item->total_sem_iva);
        $this->assertSame(221.4, $item->total_com_iva);
    }

    public function test_o_stock_entra_ao_preco_pago_e_nao_ao_de_tabela(): void
    {
        $produto = Produto::query()->create([
            'nome' => 'Adubo foliar 20L',
            'tipo' => 'fertilizante',
            'unidade_medida' => 'L',
            'custo_unitario' => 100,
        ]);

        $despesa = Despesa::query()->create([
            'titulo' => 'Fatura de teste',
            'numero_fatura' => 'FT 1/1',
            'fornecedor' => 'Casa Queridos',
            'valor' => 190.8,
            'data' => '2026-09-09',
            'categoria' => 'fertilizantes',
        ]);

        $despesa->items()->create([
            'descricao' => 'Adubo foliar 20L',
            'quantidade' => 2,
            'preco_unitario' => 100,
            'desconto_percentagem' => 10,
            'iva_percentagem' => 6,
            'produto_id' => $produto->id,
        ]);

        app(MovimentoStockService::class)->processarEntradas($despesa->load('items.produto'));

        $this->assertDatabaseHas('movimento_stocks', [
            'produto_id' => $produto->id,
            'custo_unitario' => 90,
        ]);
        $this->assertSame(2.0, (float) Stock::query()->where('produto_id', $produto->id)->value('quantidade'));
    }

    public function test_o_ecra_de_despesas_grava_o_desconto_e_o_total_bate_certo(): void
    {
        $this->autenticar();

        $this->post(route('app.despesas.store'), [
            'titulo' => 'Casa Queridos',
            'numero_fatura' => 'FT 1100/135909',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-07-20',
            'categoria' => 'fitofarmaceuticos',
            'items' => [[
                'descricao' => 'ERUNE primetanil - 5 LT',
                'quantidade' => 2,
                'preco_unitario' => 103.5,
                'desconto_percentagem' => 10,
                'iva_percentagem' => 6,
            ]],
        ])->assertRedirect();

        $despesa = Despesa::query()->firstOrFail();

        // 2 x 103,50 = 207,00 menos 10% = 186,30, mais 6% de IVA = 197,48
        $this->assertSame('197.48', (string) $despesa->valor);
        $this->assertSame('10.00', (string) $despesa->items->first()->desconto_percentagem);
    }

    public function test_a_api_de_faturas_aceita_o_desconto(): void
    {
        $user = $this->autenticar();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['faturas:write', 'custos:write']);

        $this->postJson('/api/v1/faturas', [
            'numero_fatura' => 'FT 1100/135909',
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-07-20',
            'categoria' => 'fitofarmaceuticos',
            'linhas' => [[
                'descricao' => 'ERUNE primetanil - 5 LT',
                'quantidade' => 2,
                'preco_unitario' => 103.5,
                'desconto_percentagem' => 10,
                'iva_percentagem' => 6,
            ]],
        ])->assertCreated();

        $item = FaturaItem::query()->firstOrFail();

        $this->assertSame('10.00', (string) $item->desconto_percentagem);
        $this->assertSame(93.15, $item->preco_liquido);
        $this->assertSame('197.48', (string) Despesa::query()->value('valor'));
    }

    private function autenticar(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['name' => 'admin']);
        $user->roles()->attach($role);

        $this->actingAs($user);

        return $user;
    }
}
