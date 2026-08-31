<?php

namespace Tests\Feature;

use App\Models\Compromisso;
use App\Models\Role;
use App\Models\User;
use App\Services\GeradorCompromissos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_serie_anual_gera_ocorrencias_dentro_do_horizonte(): void
    {
        $serie = Compromisso::query()->create([
            'titulo' => 'IMI',
            'categoria' => 'pagamento',
            'data' => now()->startOfYear()->addMonths(4)->toDateString(),
            'valor' => 340,
            'recorrencia' => 'anual',
        ]);

        $gerados = app(GeradorCompromissos::class)->gerar($serie, 36);

        // 36 meses de horizonte: 3 ocorrencias anuais depois da primeira.
        $this->assertCount(3, $gerados);
        $this->assertDatabaseCount('compromissos', 4);

        foreach ($gerados as $ocorrencia) {
            $this->assertSame($serie->id, $ocorrencia->compromisso_pai_id);
            $this->assertSame('nenhuma', $ocorrencia->recorrencia);
            $this->assertSame('pendente', $ocorrencia->estado);
        }
    }

    public function test_gerar_e_idempotente(): void
    {
        $serie = Compromisso::query()->create([
            'titulo' => 'Seguro',
            'categoria' => 'pagamento',
            'data' => now()->toDateString(),
            'recorrencia' => 'trimestral',
        ]);

        $gerador = app(GeradorCompromissos::class);
        $gerador->gerar($serie, 12);
        $total = Compromisso::query()->count();

        $segunda = $gerador->gerar($serie, 12);

        $this->assertSame([], $segunda);
        $this->assertDatabaseCount('compromissos', $total);
    }

    public function test_recorrencia_mensal_nao_transborda_no_fim_do_mes(): void
    {
        $serie = Compromisso::query()->create([
            'titulo' => 'Renda',
            'categoria' => 'pagamento',
            'data' => '2026-01-31',
            'recorrencia' => 'mensal',
            'recorrencia_fim' => '2026-03-31',
        ]);

        $gerados = app(GeradorCompromissos::class)->gerar($serie, 240);
        $datas = array_map(fn ($c) => $c->data->toDateString(), $gerados);

        // 31 de janeiro + 1 mes = 28 de fevereiro, e nao 3 de marco.
        $this->assertSame(['2026-02-28', '2026-03-28'], $datas);
    }

    public function test_recorrencia_para_na_data_de_fim(): void
    {
        $serie = Compromisso::query()->create([
            'titulo' => 'Prestação',
            'categoria' => 'pagamento',
            'data' => '2026-01-10',
            'recorrencia' => 'mensal',
            'recorrencia_fim' => '2026-04-10',
        ]);

        $gerados = app(GeradorCompromissos::class)->gerar($serie, 240);

        $this->assertCount(3, $gerados);
        $this->assertSame('2026-04-10', end($gerados)->data->toDateString());
    }

    public function test_concluir_cria_custo_e_a_proxima_ocorrencia(): void
    {
        $this->autenticar();

        $serie = Compromisso::query()->create([
            'titulo' => 'Segurança Social',
            'categoria' => 'pagamento',
            'entidade' => 'Segurança Social',
            'data' => now()->toDateString(),
            'valor' => 153.20,
            'recorrencia' => 'mensal',
        ]);

        $response = $this->post(route('app.calendario.concluir', $serie), [
            'valor_pago' => 153.20,
        ]);

        $response->assertRedirect();

        $serie->refresh();
        $this->assertSame('concluido', $serie->estado);
        $this->assertNotNull($serie->custo_id);

        $this->assertDatabaseHas('custos', [
            'id' => $serie->custo_id,
            'tipo' => 'outro',
            'valor' => 153.20,
            'referencia_externa' => 'compromisso-'.$serie->id,
        ]);

        $this->assertGreaterThan(0, Compromisso::query()->where('compromisso_pai_id', $serie->id)->count());
    }

    public function test_concluir_duas_vezes_nao_duplica_o_custo(): void
    {
        $this->autenticar();

        $compromisso = Compromisso::query()->create([
            'titulo' => 'IUC',
            'categoria' => 'pagamento',
            'data' => now()->toDateString(),
            'valor' => 62,
        ]);

        $this->post(route('app.calendario.concluir', $compromisso), ['valor_pago' => 62]);
        $this->post(route('app.calendario.concluir', $compromisso), ['valor_pago' => 62]);

        $this->assertDatabaseCount('custos', 1);
    }

    public function test_concluir_sem_criar_custo(): void
    {
        $this->autenticar();

        $compromisso = Compromisso::query()->create([
            'titulo' => 'Entregar IRS',
            'categoria' => 'prazo_legal',
            'data' => now()->toDateString(),
            'valor' => 0,
        ]);

        $this->post(route('app.calendario.concluir', $compromisso), ['criar_custo' => false]);

        $this->assertDatabaseCount('custos', 0);
        $this->assertSame('concluido', $compromisso->refresh()->estado);
    }

    public function test_atrasado_marca_compromissos_pendentes_vencidos(): void
    {
        $vencido = Compromisso::query()->create([
            'titulo' => 'IMI 2ª prestação',
            'categoria' => 'pagamento',
            'data' => now()->subDays(3)->toDateString(),
        ]);

        $futuro = Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => now()->addDays(3)->toDateString(),
        ]);

        $this->assertTrue($vencido->atrasado);
        $this->assertFalse($futuro->atrasado);
        $this->assertSame(1, Compromisso::query()->atrasados()->count());
    }

    public function test_pagina_do_calendario_carrega(): void
    {
        $this->autenticar();

        Compromisso::query()->create([
            'titulo' => 'IMI',
            'categoria' => 'pagamento',
            'data' => now()->toDateString(),
            'valor' => 100,
        ]);

        $this->get(route('app.calendario.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Calendario/Index')
                ->has('compromissos', 1)
                ->where('resumo.pendentes', 1));
    }

    public function test_criar_compromisso_pela_pagina_gera_a_serie(): void
    {
        $this->autenticar();

        $this->post(route('app.calendario.store'), [
            'titulo' => 'Seguro colheitas',
            'categoria' => 'pagamento',
            'data' => now()->toDateString(),
            'valor' => 200,
            'recorrencia' => 'anual',
        ])->assertRedirect();

        $this->assertDatabaseHas('compromissos', ['titulo' => 'Seguro colheitas', 'recorrencia' => 'anual']);
        $this->assertGreaterThan(1, Compromisso::query()->count());
    }

    public function test_recorrencia_personalizada_exige_intervalo_e_unidade(): void
    {
        $this->autenticar();

        $this->post(route('app.calendario.store'), [
            'titulo' => 'Adubação',
            'categoria' => 'tarefa_agricola',
            'data' => now()->toDateString(),
            'recorrencia' => 'personalizada',
        ])->assertSessionHasErrors('recorrencia_intervalo');
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
