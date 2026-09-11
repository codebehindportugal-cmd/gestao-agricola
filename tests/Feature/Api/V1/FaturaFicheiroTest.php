<?php

namespace Tests\Feature\Api\V1;

use App\Models\Despesa;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A foto da fatura registada pelo chat.
 *
 * A fatura entrava pela API e a despesa ficava sem documento nenhum: o papel
 * ficava na conversa e o caderno de campo sem prova da compra. Este endpoint e
 * por onde a foto chega, no mesmo sitio onde o ecra a guarda.
 */
class FaturaFicheiroTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_a_foto_na_despesa(): void
    {
        Storage::fake('public');
        $this->autenticarApi();
        $despesa = $this->despesa();

        $response = $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->image('fatura.jpg', 1200, 1600),
        ]);

        $response->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('dados.despesa_id', $despesa->id);

        $caminho = $despesa->fresh()->ficheiro_path;

        $this->assertNotNull($caminho, 'a despesa ficou sem foto');
        $this->assertStringStartsWith('despesas/', $caminho);
        Storage::disk('public')->assertExists($caminho);
        $this->assertSame(
            Storage::disk('public')->url($caminho),
            $response->json('dados.ficheiro_url')
        );
    }

    public function test_substituir_apaga_a_foto_anterior(): void
    {
        Storage::fake('public');
        $this->autenticarApi();
        $despesa = $this->despesa();

        $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->image('primeira.jpg'),
        ])->assertOk();

        $primeira = $despesa->fresh()->ficheiro_path;

        $response = $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->image('segunda.jpg'),
        ])->assertOk();

        $segunda = $despesa->fresh()->ficheiro_path;

        $this->assertNotSame($primeira, $segunda);
        Storage::disk('public')->assertMissing($primeira);
        Storage::disk('public')->assertExists($segunda);
        $this->assertTrue(
            collect($response->json('avisos'))->contains(fn ($a) => str_contains($a, 'substituida')),
            'tem de dizer que substituiu a anterior'
        );
    }

    public function test_aceita_pdf_e_recusa_o_resto(): void
    {
        Storage::fake('public');
        $this->autenticarApi();
        $despesa = $this->despesa();

        $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->create('fatura.pdf', 200, 'application/pdf'),
        ])->assertOk();

        $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->create('fatura.zip', 10, 'application/zip'),
        ])->assertStatus(422)->assertJsonPath('sucesso', false);
    }

    public function test_sem_ability_nao_passa(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'operador']));
        Sanctum::actingAs($user, ['colheitas:write']);

        $despesa = $this->despesa();

        $this->post("/api/v1/faturas/{$despesa->id}/ficheiro", [
            'ficheiro' => UploadedFile::fake()->image('fatura.jpg'),
        ])->assertForbidden();

        $this->assertNull($despesa->fresh()->ficheiro_path);
    }

    private function despesa(): Despesa
    {
        return Despesa::query()->create([
            'titulo' => 'Casa Queridos',
            'numero_fatura' => 'FT 1100/132690',
            'valor' => 349.59,
            'data' => '2026-04-10',
            'categoria' => 'fitofarmaceuticos',
        ]);
    }

    private function autenticarApi(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'operador']));

        Sanctum::actingAs($user, ['faturas:write']);

        return $user;
    }
}
