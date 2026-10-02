<?php

namespace Tests\Feature;

use App\Models\Despesa;
use App\Models\Fornecedor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quanto se deve a cada fornecedor: faturas menos recibos.
 */
class ContaCorrenteFornecedoresTest extends TestCase
{
    use RefreshDatabase;

    private function fatura(string $numero, float $valor, string $fornecedor = 'Casa Queridos', string $data = '2026-08-01'): Despesa
    {
        return Despesa::query()->create([
            'titulo' => "Compra {$numero}",
            'numero_fatura' => $numero,
            'fornecedor' => $fornecedor,
            'valor' => $valor,
            'data' => $data,
            'categoria' => 'fitofarmaceuticos',
        ]);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->create(['name' => 'operador']));
        Sanctum::actingAs($user, ['pagamentos:write']);

        return $user;
    }

    public function test_nomes_diferentes_do_mesmo_fornecedor_ficam_na_mesma_ficha(): void
    {
        $a = $this->fatura('FT 1/1', 100, 'CASA QUERIDOS, LDA.');
        $b = $this->fatura('FT 1/2', 50, 'Casa Queridos');

        $this->assertNotNull($a->fornecedor_id);
        $this->assertSame($a->fornecedor_id, $b->fornecedor_id);
        $this->assertSame(1, Fornecedor::query()->count());
    }

    public function test_fatura_recibo_nao_entra_na_divida(): void
    {
        $fr = $this->fatura('FR 4002A26', 80);
        $ft = $this->fatura('FT 1100/132690', 200);

        $this->assertTrue($fr->pago_no_ato);
        $this->assertFalse((bool) $ft->pago_no_ato);

        $this->autenticarApi();
        $this->getJson('/api/v1/fornecedores/saldos')
            ->assertOk()
            ->assertJsonPath('dados.total_em_divida', 200)
            ->assertJsonPath('dados.fornecedores.0.faturas_em_aberto', 1);
    }

    public function test_recibo_paga_as_faturas_que_indica_e_devolve_o_saldo(): void
    {
        $this->fatura('FT 1100/1', 300, data: '2026-07-01');
        $this->fatura('FT 1100/2', 150, data: '2026-07-15');
        $this->fatura('FT 1100/3', 90, data: '2026-08-01');
        $this->autenticarApi();

        $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'casa queridos',
            'data' => '2026-09-30',
            'valor' => 450,
            'numero_recibo' => 'RC 1/77',
            'metodo' => 'transferencia',
            // O recibo escreve a serie de outra maneira.
            'faturas' => ['FT 1100/1', 'FT 2'],
        ])->assertCreated()
            ->assertJsonPath('dados.saldo.em_divida', 90)
            ->assertJsonPath('dados.saldo.faturas_em_aberto', 1)
            ->assertJsonCount(2, 'dados.pagamento.faturas');

        // Repetir o mesmo recibo nao paga duas vezes.
        $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-09-30',
            'valor' => 450,
            'numero_recibo' => 'RC 1/77',
        ])->assertOk()->assertJsonPath('dados.ja_existia', true);

        $this->getJson('/api/v1/fornecedores/Casa Queridos/conta')
            ->assertOk()
            ->assertJsonPath('dados.saldo', 90)
            ->assertJsonPath('dados.faturas_em_aberto.0.numero_fatura', 'FT 1100/3');
    }

    public function test_pagamento_parcial_e_imputacao_automatica_pelas_mais_antigas(): void
    {
        $this->fatura('FT 9/1', 100, 'AgroExclusive', '2026-06-01');
        $this->fatura('FT 9/2', 100, 'AgroExclusive', '2026-06-10');
        $this->autenticarApi();

        $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'AgroExclusive',
            'data' => '2026-07-01',
            'valor' => 150,
            'imputar_automaticamente' => true,
        ])->assertCreated()
            ->assertJsonPath('dados.saldo.em_divida', 50);

        $conta = $this->getJson('/api/v1/fornecedores/AgroExclusive/conta')->json('dados');
        $this->assertCount(1, $conta['faturas_em_aberto']);
        $this->assertSame('parcial', $conta['faturas_em_aberto'][0]['estado']);
        $this->assertEquals(50, $conta['faturas_em_aberto'][0]['em_falta']);
    }

    public function test_recibo_de_fatura_ainda_nao_registada_liga_quando_ela_entra(): void
    {
        $this->fatura('FT 5/1', 40);
        $this->autenticarApi();

        $resposta = $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-09-01',
            'valor' => 100,
            'faturas' => ['FT 5/1', 'FT 5/2'],
        ])->assertCreated()
            ->assertJsonPath('dados.saldo.em_divida', -60);

        $this->assertNotEmpty($resposta->json('avisos'));

        $nova = $this->fatura('FT 5/2', 60, data: '2026-08-20');

        $this->assertEquals(60, (float) $nova->pagamentos()->sum('pagamento_fornecedor_despesa.valor'));
        $this->getJson('/api/v1/fornecedores/saldos')
            ->assertJsonPath('dados.fornecedores.0.saldo', 0)
            ->assertJsonPath('dados.fornecedores.0.faturas_em_aberto', 0);
    }

    public function test_anular_recibo_reabre_as_faturas(): void
    {
        $this->fatura('FT 7/1', 70);
        $this->autenticarApi();

        $id = $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'Casa Queridos',
            'data' => '2026-09-01',
            'valor' => 70,
            'faturas' => ['FT 7/1'],
        ])->json('dados.pagamento.id');

        $this->deleteJson("/api/v1/pagamentos/{$id}")
            ->assertOk()
            ->assertJsonPath('dados.saldo.em_divida', 70)
            ->assertJsonPath('dados.saldo.faturas_em_aberto', 1);
    }

    public function test_fornecedor_desconhecido_devolve_422(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/pagamentos', [
            'fornecedor' => 'Ninguem',
            'data' => '2026-09-01',
            'valor' => 10,
        ])->assertStatus(422);
    }

    public function test_ecra_regista_recibo_e_junta_fornecedores(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->create(['name' => 'admin']));

        $a = $this->fatura('FT 3/1', 120, 'Casa Queridos');
        $b = $this->fatura('FT 3/2', 30, 'Casa dos Queridos');
        $this->assertNotSame($a->fornecedor_id, $b->fornecedor_id);

        $this->actingAs($user)
            ->post(route('app.fornecedores.juntar', $a->fornecedor_id), ['outro_id' => $b->fornecedor_id])
            ->assertRedirect();

        $this->assertSame($a->fornecedor_id, $b->fresh()->fornecedor_id);

        $this->actingAs($user)
            ->post(route('app.fornecedores.pagamentos.store', $a->fornecedor_id), [
                'data' => '2026-09-10',
                'valor' => 120,
                'faturas' => [['despesa_id' => $a->id]],
            ])
            ->assertRedirect(route('app.fornecedores.show', $a->fornecedor_id));

        $saldo = app(\App\Services\ContaCorrenteFornecedores::class)->saldos()->firstWhere('id', $a->fornecedor_id);
        $this->assertEquals(30, $saldo['saldo']);

        $this->actingAs($user)->get(route('app.fornecedores.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Fornecedores/Index')->where('resumo.em_divida', 30));

        $this->actingAs($user)->get(route('app.fornecedores.show', $a->fornecedor_id))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Fornecedores/Show')->has('faturas', 2)->has('pagamentos', 1)->has('extrato', 3));
    }
}
