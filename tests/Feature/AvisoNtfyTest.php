<?php

namespace Tests\Feature;

use App\Models\Compromisso;
use App\Models\CompromissoAviso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvisoNtfyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ntfy.enabled', true);
        config()->set('ntfy.url', 'https://ntfy.exemplo.pt');
        config()->set('ntfy.topic', 'agro-teste');
        config()->set('ntfy.token', null);
        config()->set('ntfy.marcos_dias', [30, 7, 1]);
    }

    public function test_avisa_aos_30_7_e_1_dia_e_nao_avisa_nos_outros_dias(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $compromisso = Compromisso::query()->create([
            'titulo' => 'IMI 2a prestacao',
            'categoria' => 'pagamento',
            'data' => '2026-10-31',
            'valor' => 340,
        ]);

        // 30 dias antes: avisa.
        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertSuccessful();
        Http::assertSentCount(1);

        // 29 dias antes: nao ha marco, nada sai.
        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-02'])->assertSuccessful();
        Http::assertSentCount(1);

        // 7 dias antes: avisa outra vez.
        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-24'])->assertSuccessful();
        Http::assertSentCount(2);

        // 1 dia antes: avisa a terceira e ultima vez.
        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-30'])->assertSuccessful();
        Http::assertSentCount(3);

        $this->assertSame(
            [1, 7, 30],
            CompromissoAviso::query()->where('compromisso_id', $compromisso->id)
                ->pluck('dias_antes')->sort()->values()->all()
        );
    }

    public function test_nao_repete_o_mesmo_aviso_se_correr_duas_vezes_no_mesmo_dia(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertSuccessful();
        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('compromisso_avisos', 1);
    }

    public function test_envia_uma_so_notificacao_com_tudo_e_prioridade_de_atraso(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        Compromisso::query()->create([
            'titulo' => 'Seguranca social',
            'categoria' => 'pagamento',
            'data' => '2026-09-25',
            'valor' => 152.5,
        ]);
        Compromisso::query()->create([
            'titulo' => 'Poda de inverno',
            'categoria' => 'tarefa_agricola',
            'data' => '2026-10-02',
        ]);
        Compromisso::query()->create([
            'titulo' => 'IMI 1a prestacao',
            'categoria' => 'pagamento',
            'data' => '2026-09-20',
            'valor' => 340,
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $pedido) {
            $mensagem = $pedido->body();

            $this->assertSame('https://ntfy.exemplo.pt/agro-teste', $pedido->url());
            $this->assertSame('Gestao Agricola - 3 compromissos', $pedido->header('Title')[0]);
            // Um atrasado eleva a prioridade.
            $this->assertSame('high', $pedido->header('Priority')[0]);
            $this->assertSame('rotating_light', $pedido->header('Tags')[0]);

            $this->assertStringContainsString('Em atraso', $mensagem);
            $this->assertStringContainsString('IMI 1a prestacao', $mensagem);
            $this->assertStringContainsString('Seguranca social', $mensagem);
            $this->assertStringContainsString('Amanha', $mensagem);
            $this->assertStringContainsString('Poda de inverno', $mensagem);
            $this->assertStringContainsString('340,00 EUR', $mensagem);

            return true;
        });
    }

    public function test_compromisso_concluido_nao_gera_aviso(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        Compromisso::query()->create([
            'titulo' => 'IMI ja pago',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
            'estado' => 'concluido',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_falha_do_servidor_nao_marca_como_avisado(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])->assertFailed();

        // Nada gravado: no dia seguinte tenta outra vez.
        $this->assertDatabaseCount('compromisso_avisos', 0);
    }

    public function test_sem_topico_configurado_falha_com_mensagem_clara(): void
    {
        Http::fake();
        config()->set('ntfy.topic', null);

        Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])
            ->expectsOutputToContain('NTFY_TOPIC')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_modo_seco_nao_envia_nem_grava(): void
    {
        Http::fake();

        Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01', '--seco' => true])
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('compromisso_avisos', 0);
    }

    /** O interruptor por tipo de aviso, igual ao do painel Ateneya. */
    public function test_desligar_os_avisos_de_compromissos_no_env_para_tudo(): void
    {
        Http::fake();
        config()->set('ntfy.avisos.compromissos', false);

        Compromisso::query()->create([
            'titulo' => 'Seguro tractor',
            'categoria' => 'pagamento',
            'data' => '2026-10-08',
        ]);

        $this->artisan('ntfy:compromissos', ['--data' => '2026-10-01'])
            ->expectsOutputToContain('NTFY_AVISA_COMPROMISSOS')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('compromisso_avisos', 0);
    }

    public function test_o_comando_de_teste_envia_para_o_topico(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->artisan('ntfy:test', ['mensagem' => 'ola'])->assertSuccessful();

        Http::assertSent(function (Request $pedido) {
            $this->assertSame('https://ntfy.exemplo.pt/agro-teste', $pedido->url());
            $this->assertSame('ola', $pedido->body());

            return true;
        });
    }
}
