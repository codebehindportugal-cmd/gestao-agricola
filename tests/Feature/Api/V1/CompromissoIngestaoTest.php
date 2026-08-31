<?php

namespace Tests\Feature\Api\V1;

use App\Models\Compromisso;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompromissoIngestaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_lote_de_compromissos(): void
    {
        $this->autenticarApi();

        $response = $this->postJson('/api/v1/compromissos', [
            'compromissos' => [
                [
                    'titulo' => 'IMI - 1ª prestação',
                    'categoria' => 'pagamento',
                    'tipo' => 'IMI',
                    'entidade' => 'Autoridade Tributária',
                    'data' => '2026-06-01',
                    'valor' => 340,
                    'recorrencia' => 'anual',
                    'referencia_externa' => 'imi-2026-1',
                ],
                [
                    'titulo' => 'Segurança Social',
                    'categoria' => 'pagamento',
                    'data' => '2026-09-20',
                    'valor' => 153.20,
                    'recorrencia' => 'mensal',
                    'referencia_externa' => 'ss-2026-09',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(2, 'dados.compromissos');

        $this->assertDatabaseHas('compromissos', ['titulo' => 'IMI - 1ª prestação', 'recorrencia' => 'anual']);
        // As ocorrencias geradas contam para alem das duas series.
        $this->assertGreaterThan(2, Compromisso::query()->count());
    }

    public function test_idempotencia_pela_referencia_externa(): void
    {
        $this->autenticarApi();

        $payload = [
            'titulo' => 'Seguro do tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-15',
            'valor' => 420,
            'referencia_externa' => 'seguro-tractor-2026',
        ];

        $this->postJson('/api/v1/compromissos', $payload)->assertCreated();
        $this->postJson('/api/v1/compromissos', $payload)
            ->assertCreated()
            ->assertJsonFragment(['compromisso ja registado (seguro-tractor-2026)'])
            ->assertJsonCount(0, 'dados.compromissos');

        $this->assertDatabaseCount('compromissos', 1);
    }

    public function test_categoria_invalida_devolve_422(): void
    {
        $this->autenticarApi();

        $this->postJson('/api/v1/compromissos', [
            'titulo' => 'Qualquer coisa',
            'categoria' => 'inventada',
            'data' => '2026-10-15',
        ])->assertStatus(422)->assertJsonPath('sucesso', false);
    }

    public function test_listagem_filtra_por_periodo_e_estado(): void
    {
        $this->autenticarApi();

        Compromisso::query()->create([
            'titulo' => 'Dentro', 'categoria' => 'pagamento', 'data' => '2026-10-10',
        ]);
        Compromisso::query()->create([
            'titulo' => 'Fora', 'categoria' => 'pagamento', 'data' => '2026-12-10',
        ]);

        $this->getJson('/api/v1/compromissos?de=2026-10-01&ate=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1, 'dados.compromissos')
            ->assertJsonPath('dados.compromissos.0.titulo', 'Dentro');
    }

    public function test_concluir_pela_api_cria_custo(): void
    {
        $this->autenticarApi();

        $compromisso = Compromisso::query()->create([
            'titulo' => 'IUC',
            'categoria' => 'pagamento',
            'data' => '2026-09-30',
            'valor' => 62,
        ]);

        $this->postJson("/api/v1/compromissos/{$compromisso->id}/concluir", ['valor_pago' => 62])
            ->assertOk()
            ->assertJsonPath('dados.compromisso.estado', 'concluido')
            ->assertJsonPath('dados.custo.valor', '62.00');

        $this->assertDatabaseCount('custos', 1);
    }

    public function test_leitura_exige_token(): void
    {
        $this->getJson('/api/v1/compromissos')->assertUnauthorized();
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['custos:write']);

        return $user;
    }
}
