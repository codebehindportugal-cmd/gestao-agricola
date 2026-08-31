<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\Terreno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CadastroProtegidoTest extends TestCase
{
    use RefreshDatabase;

    public static function rotasDeLeitura(): array
    {
        return [
            ['/api/v1/terrenos'],
            ['/api/v1/parcelas'],
            ['/api/v1/culturas'],
            ['/api/v1/operacoes'],
            ['/api/v1/maquinas'],
            ['/api/v1/alfaias'],
            ['/api/v1/campanhas'],
            ['/api/v1/funcionarios'],
            ['/api/v1/equipas'],
            ['/api/v1/produtos'],
        ];
    }

    /**
     * @dataProvider rotasDeLeitura
     */
    public function test_leitura_do_cadastro_exige_token(string $rota): void
    {
        $this->getJson($rota)->assertUnauthorized();
    }

    public function test_escrita_no_cadastro_exige_token(): void
    {
        $this->postJson('/api/v1/terrenos', ['nome' => 'Invasor', 'area_total' => 1])
            ->assertUnauthorized();

        $this->assertDatabaseCount('terrenos', 0);
    }

    public function test_eliminar_terreno_exige_token(): void
    {
        $terreno = Terreno::query()->create(['nome' => 'Terreno Norte', 'area_total' => 10]);

        $this->deleteJson("/api/v1/terrenos/{$terreno->id}")->assertUnauthorized();

        $this->assertDatabaseHas('terrenos', ['id' => $terreno->id, 'deleted_at' => null]);
    }

    public function test_utilizador_sem_role_de_escrita_nao_escreve_no_cadastro(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'visitante']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/terrenos', ['nome' => 'Invasor', 'area_total' => 1])
            ->assertForbidden();

        $this->assertDatabaseCount('terrenos', 0);
    }

    public function test_utilizador_autenticado_le_o_cadastro(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);
        Sanctum::actingAs($user, ['*']);

        Terreno::query()->create(['nome' => 'Terreno Norte', 'area_total' => 10]);

        $this->getJson('/api/v1/terrenos')->assertOk();
    }
}
