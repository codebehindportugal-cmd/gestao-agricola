<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_pedido_sem_token_devolve_401(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertUnauthorized();
    }

    public function test_pedido_com_token_valido_passa(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'operador']);
        $user->roles()->attach($role);

        Sanctum::actingAs($user, ['custos:write']);

        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson([
                'sucesso' => true,
                'dados' => [
                    'mensagem' => 'API v1 autenticada',
                ],
                'avisos' => [],
                'erros' => [],
            ]);
    }
}
