<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoCampanhaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A /api/v1/trabalhos grava o valor da mao de obra duas vezes: em
     * operacoes.custo_real e num Custo com operacao_id. O total da campanha
     * nao pode contar os dois.
     */
    public function test_custo_ligado_a_operacao_nao_e_contado_duas_vezes(): void
    {
        $campanha = $this->campanhaGeral();
        $parcela = $campanha->parcelas->first();

        $operacao = Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'parcela_id' => $parcela->id,
            'tipo' => 'colheita',
            'data_hora_inicio' => '2026-08-15 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 7680,
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'operacao_id' => $operacao->id,
            'descricao' => 'Apanha da fruta - mao de obra',
            'tipo' => 'mao_obra',
            'valor' => 7680,
            'data_custo' => '2026-08-15',
        ]);

        $this->assertSame(7680.0, $campanha->fresh()->custo_total_calculado);
    }

    /** Um custo avulso (fatura, IMI) nao esta ligado a operacao e soma-se na mesma. */
    public function test_custo_avulso_continua_a_somar(): void
    {
        $campanha = $this->campanhaGeral();
        $parcela = $campanha->parcelas->first();

        $operacao = Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'parcela_id' => $parcela->id,
            'tipo' => 'poda',
            'data_hora_inicio' => '2025-12-02 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 500,
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'operacao_id' => $operacao->id,
            'descricao' => 'Poda - mao de obra',
            'tipo' => 'mao_obra',
            'valor' => 500,
            'data_custo' => '2025-12-02',
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'descricao' => 'Fatura de adubo',
            'tipo' => 'material',
            'valor' => 220.5,
            'data_custo' => '2026-01-10',
        ]);

        $this->assertSame(720.5, $campanha->fresh()->custo_total_calculado);
    }

    /** Operacao sem custo_real mas com custos ligados: o valor nao se perde. */
    public function test_operacao_sem_custo_real_usa_os_custos_ligados(): void
    {
        $campanha = $this->campanhaGeral();
        $parcela = $campanha->parcelas->first();

        $operacao = Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'parcela_id' => $parcela->id,
            'tipo' => 'fertilização',
            'data_hora_inicio' => '2026-03-01 08:00:00',
            'estado' => 'concluida',
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'operacao_id' => $operacao->id,
            'descricao' => 'Gasoleo',
            'tipo' => 'combustivel',
            'valor' => 90,
            'data_custo' => '2026-03-01',
        ]);

        $this->assertSame(90.0, $campanha->fresh()->custo_total_calculado);
    }

    /** O resumo da pagina de operacoes mostrava " - 2026" nas campanhas gerais. */
    public function test_resumo_de_operacoes_mostra_o_nome_da_campanha_geral(): void
    {
        $this->autenticar();
        $campanha = $this->campanhaGeral();

        Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'parcela_id' => $campanha->parcelas->first()->id,
            'tipo' => 'tratamento fitossanitário',
            'data_hora_inicio' => '2026-05-02 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 120,
        ]);

        $this->get(route('app.operacoes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cadernoCampo.0.nome', 'Pereiras 2026')
                ->where('cadernoCampo.0.tratamentos', 1)
                ->where('campanhas.0.nome', 'Pereiras 2026'));
    }

    /** A pagina da campanha e o PDF de custos usam a mesma regra. */
    public function test_pagina_e_pdf_da_campanha_nao_duplicam_o_custo(): void
    {
        $this->autenticar();
        $campanha = $this->campanhaGeral();

        $operacao = Operacao::query()->create([
            'campanha_id' => $campanha->id,
            'parcela_id' => $campanha->parcelas->first()->id,
            'tipo' => 'colheita',
            'data_hora_inicio' => '2026-08-15 08:00:00',
            'estado' => 'concluida',
            'custo_real' => 8480,
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'operacao_id' => $operacao->id,
            'descricao' => 'Apanha - mao de obra',
            'tipo' => 'mao_obra',
            'valor' => 8480,
            'data_custo' => '2026-08-15',
        ]);

        Custo::query()->create([
            'campanha_id' => $campanha->id,
            'descricao' => 'Seguro',
            'tipo' => 'outro',
            'valor' => 300,
            'data_custo' => '2026-01-05',
        ]);

        $this->get(route('app.campanhas.show', $campanha))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resumo.custo_operacoes', 8480)
                ->where('resumo.custo_diretos', 300)
                ->where('resumo.custo_total', 8780));

        $this->get(route('app.campanhas.custos-pdf', $campanha))->assertOk();
    }

    private function campanhaGeral(): Campanha
    {
        $terreno = Terreno::query()->create(['nome' => 'Cumeira 1', 'area_total' => 5]);
        $parcela = Parcela::query()->create([
            'terreno_id' => $terreno->id, 'nome' => 'Cumeira', 'area_total' => 1.69, 'area_util' => 1.69,
        ]);

        $campanha = Campanha::query()->create([
            'nome' => 'Pereiras 2026',
            'ano' => 2026,
            'data_inicio' => '2025-10-01',
            'data_fim' => '2026-09-30',
            'status' => 'em_curso',
        ]);
        $campanha->parcelas()->sync([$parcela->id]);

        return $campanha->fresh(['parcelas']);
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
