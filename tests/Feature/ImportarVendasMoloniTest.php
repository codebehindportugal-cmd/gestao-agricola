<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Receita;
use App\Models\Role;
use App\Models\User;
use App\Services\Moloni\EspecieDoArtigo;
use App\Services\Moloni\ImportadorVendasMoloni;
use App\Services\ResumoPorEspecieService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportarVendasMoloniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.moloni', [
            'url' => 'https://api.moloni.pt/v1',
            'client_id' => 'dev',
            'client_secret' => 'segredo',
            'username' => 'andre',
            'password' => 'pass',
            'company_id' => null,
        ]);
        Cache::flush();
        $this->fingirMoloni();
    }

    public function test_importa_linhas_com_especie_pelo_nome_do_artigo(): void
    {
        $campanha = $this->campanha();

        $resumo = app(ImportadorVendasMoloni::class)->importar(
            $campanha, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-30')
        );

        // A FT fechada de setembro e a FS; o rascunho e a fatura de 2024 ficam de fora.
        $this->assertSame(2, $resumo['documentos']);
        $this->assertSame(3, $resumo['criadas']);
        $this->assertSame(1, $resumo['sem_especie']);

        $pera = Receita::query()->where('descricao', 'Pera Rocha cal. 60/65')->firstOrFail();
        $this->assertSame('Pereira', $pera->especie);
        $this->assertSame('venda_colheita', $pera->tipo);
        // 1000 kg a 0,60 com 10% de desconto na linha e 5% global.
        $this->assertSame('513.00', $pera->valor);
        $this->assertSame('1000.000', $pera->quantidade);
        $this->assertSame('kg', $pera->unidade);
        $this->assertSame('FT A/12', $pera->documento);
        $this->assertSame('Frutas do Oeste, Lda', $pera->comprador_nome);
        $this->assertSame($campanha->id, $pera->campanha_id);

        $gala = Receita::query()->where('descricao', 'Maçã Gala')->firstOrFail();
        $this->assertSame('Macieira', $gala->especie);

        $caixas = Receita::query()->where('descricao', 'Caixas de cartão')->firstOrFail();
        $this->assertNull($caixas->especie);
        $this->assertSame('outro', $caixas->tipo);
    }

    public function test_correr_outra_vez_nao_duplica(): void
    {
        $campanha = $this->campanha();
        $importador = app(ImportadorVendasMoloni::class);
        $de = CarbonImmutable::parse('2025-10-01');
        $ate = CarbonImmutable::parse('2026-09-30');

        $importador->importar($campanha, $de, $ate);
        $segunda = $importador->importar($campanha, $de, $ate);

        $this->assertSame(0, $segunda['criadas']);
        $this->assertSame(3, $segunda['existentes']);
        $this->assertSame(3, Receita::query()->count());

        // O token ficou em cache: um so pedido de grant nas duas importacoes.
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($par) => str_contains($par[0]->url(), '/grant/'))->count());
    }

    public function test_venda_importada_entra_na_especie_certa_do_resumo(): void
    {
        $campanha = $this->campanha();
        app(ImportadorVendasMoloni::class)->importar(
            $campanha, CarbonImmutable::parse('2025-10-01'), CarbonImmutable::parse('2026-09-30')
        );

        $resumo = app(ResumoPorEspecieService::class)->paraCampanha($campanha->fresh());
        $porEspecie = collect($resumo['especies'])->keyBy('especie');

        $this->assertSame(513.0, $porEspecie['Pereira']['vendas']);
        $this->assertSame(1000.0, $porEspecie['Pereira']['kg_vendidos']);
        $this->assertSame(180.0, $porEspecie['Macieira']['vendas']);
    }

    public function test_botao_importa_para_a_campanha_actual(): void
    {
        $this->travelTo('2026-09-26');
        $campanha = $this->campanha();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));

        $this->actingAs($user)
            ->from(route('app.despesas.index'))
            ->post(route('app.despesas.vendas.moloni'))
            ->assertRedirect(route('app.despesas.index'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, '3 venda(s) nova(s)'));

        $this->assertSame(3, Receita::query()->where('campanha_id', $campanha->id)->count());
    }

    public function test_sem_credenciais_mostra_erro_e_nao_rebenta(): void
    {
        config()->set('services.moloni.client_id', null);
        config()->set('services.moloni.company_id', 5);
        $this->campanha();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));

        $this->actingAs($user)
            ->from(route('app.despesas.index'))
            ->post(route('app.despesas.vendas.moloni'))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'MOLONI_CLIENT_ID'));
    }

    public function test_especie_pelo_nome(): void
    {
        $this->assertSame('Pereira', EspecieDoArtigo::deNome('PÊRA ROCHA DO OESTE DOP'));
        $this->assertSame('Pereira', EspecieDoArtigo::deNome('Peras cat. II'));
        $this->assertSame('Macieira', EspecieDoArtigo::deNome('Maçã-Fuji cal.70'));
        $this->assertSame('Macieira', EspecieDoArtigo::deNome('Royal Gala'));
        $this->assertSame('Macieira', EspecieDoArtigo::deNome('Golden 65/70'));
        $this->assertNull(EspecieDoArtigo::deNome('Transporte'));
        // "Operação" contem "pera" mas nao e pera.
        $this->assertNull(EspecieDoArtigo::deNome('Operação de calibragem'));
    }

    private function campanha(): Campanha
    {
        return Campanha::query()->create([
            'nome' => '2025/2026', 'ano' => 2026,
            'data_inicio' => '2025-10-01', 'data_fim' => '2026-09-30', 'status' => 'em_curso',
        ]);
    }

    private function fingirMoloni(): void
    {
        Http::fake(function (Request $pedido) {
            $url = $pedido->url();
            $corpo = $pedido->data();

            if (str_contains($url, '/grant/')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600, 'token_type' => 'bearer', 'refresh_token' => 'ref']);
            }

            if (str_contains($url, '/companies/getAll/')) {
                return Http::response([['company_id' => 77, 'name' => 'Exploração']]);
            }

            if (str_contains($url, '/invoices/getAll/')) {
                if ((int) ($corpo['offset'] ?? 0) > 0 || (int) $corpo['year'] !== 2026) {
                    return Http::response([]);
                }

                return Http::response([
                    ['document_id' => 1001, 'number' => 12, 'date' => '2026-09-10T00:00:00+0100', 'status' => 1, 'entity_name' => 'Frutas do Oeste, Lda'],
                    ['document_id' => 1002, 'number' => 13, 'date' => '2026-09-12', 'status' => 0, 'entity_name' => 'Rascunho'],
                ]);
            }

            if (str_contains($url, '/invoices/getOne/')) {
                return Http::response([
                    'document_id' => 1001, 'number' => 12, 'document_set_name' => 'A', 'date' => '2026-09-10',
                    'entity_name' => 'Frutas do Oeste, Lda', 'global_discount' => 5,
                    'products' => [
                        ['name' => 'Pera Rocha cal. 60/65', 'qty' => 1000, 'price' => 0.6, 'discount' => 10],
                        ['name' => 'Caixas de cartão', 'qty' => 20, 'price' => 1.5, 'discount' => 0, 'measurement_unit' => ['short_name' => 'Un']],
                    ],
                ]);
            }

            if (str_contains($url, '/simplifiedInvoices/getAll/')) {
                if ((int) ($corpo['offset'] ?? 0) > 0 || (int) $corpo['year'] !== 2026) {
                    return Http::response([]);
                }

                return Http::response([
                    ['document_id' => 2001, 'number' => 3, 'date' => '2026-09-20', 'status' => 1, 'entity_name' => 'Consumidor final'],
                ]);
            }

            if (str_contains($url, '/simplifiedInvoices/getOne/')) {
                return Http::response([
                    'document_id' => 2001, 'number' => 3, 'document_set_name' => 'B', 'date' => '2026-09-20',
                    'entity_name' => 'Consumidor final',
                    'products' => [
                        ['name' => 'Maçã Gala', 'qty' => 300, 'price' => 0.6, 'discount' => 0],
                    ],
                ]);
            }

            return Http::response([]);
        });
    }
}
