<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\PaperInvoice\PaperInvoiceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FaturaExtracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolve_cabecalho_e_linhas_da_fatura(): void
    {
        $this->autenticar();
        $this->fingirExtractor();

        $this->post(route('app.despesas.extrair-fatura'), [
            'ficheiro' => UploadedFile::fake()->image('fatura.jpg'),
        ])->assertOk()
            ->assertJsonPath('numero_fatura', 'FT 1000/1755')
            // O extractor devolve dd/mm/aaaa; o campo de data do formulario quer ISO.
            ->assertJsonPath('data', '2026-09-08')
            ->assertJsonPath('linhas.0.descricao', 'Adubo foliar 20L')
            ->assertJsonPath('linhas.0.quantidade', 2)
            ->assertJsonPath('linhas.0.iva_percentagem', 6)
            ->assertJsonPath('fonte', 'ocr')
            ->assertJsonCount(2, 'linhas');
    }

    /** Uma taxa de IVA lida a torto entraria em silencio e falseava a despesa. */
    public function test_taxa_de_iva_fora_das_legais_fica_a_zero(): void
    {
        $this->autenticar();
        $this->fingirExtractor();

        $this->post(route('app.despesas.extrair-fatura'), [
            'ficheiro' => UploadedFile::fake()->image('fatura.jpg'),
        ])->assertOk()
            ->assertJsonPath('linhas.1.iva_percentagem', 0);
    }

    public function test_exige_um_ficheiro(): void
    {
        $this->autenticar();

        $this->post(route('app.despesas.extrair-fatura'), [])
            ->assertSessionHasErrors('ficheiro');
    }

    public function test_exige_sessao_iniciada(): void
    {
        $this->post(route('app.despesas.extrair-fatura'), [
            'ficheiro' => UploadedFile::fake()->image('fatura.jpg'),
        ])->assertRedirect(route('login'));
    }

    /** O OCR corre programas externos; nos testes o que interessa e o formato da resposta. */
    private function fingirExtractor(): void
    {
        $this->instance(PaperInvoiceExtractor::class, new class extends PaperInvoiceExtractor
        {
            public function extract(string $documentPath): array
            {
                return [
                    'supplier' => ['name' => 'Agroexclusive', 'taxNumber' => '507123456'],
                    'invoice' => [
                        'number' => 'FT 1000/1755',
                        'date' => '08/09/2026',
                        'total' => 460.04,
                        'vatTotal' => 31.85,
                    ],
                    'products' => [
                        ['description' => 'Adubo foliar 20L', 'quantity' => 2, 'unitPrice' => 120.0, 'vatRate' => 6, 'lineTotal' => 254.40, 'confidence' => 0.85],
                        ['description' => 'Nitrato de calcio 25kg', 'quantity' => 4, 'unitPrice' => 48.5, 'vatRate' => 17, 'lineTotal' => 205.64, 'confidence' => 0.75],
                    ],
                    'confidence' => 0.8,
                    'needsManualReview' => false,
                    'warnings' => [],
                ];
            }
        });
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
